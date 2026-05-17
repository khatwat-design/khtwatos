<?php

namespace App\Services;

use App\Models\ChatTaskLink;
use App\Models\DirectMessage;
use App\Models\PrivateChatMessage;
use App\Models\TeamChatMessage;
use Illuminate\Support\Collection;

class ChatTaskCardEnricher
{
    /**
     * @param  Collection<int, array<string, mixed>>  $messages
     * @return Collection<int, array<string, mixed>>
     */
    public function enrich(Collection $messages): Collection
    {
        if ($messages->isEmpty()) {
            return $messages;
        }

        $byType = [];
        foreach ($messages as $index => $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $type = $this->messageTypeForRow($row);
            if ($type === null) {
                continue;
            }
            $byType[$type][] = $id;
        }

        if ($byType === []) {
            return $messages;
        }

        $cardsByKey = [];
        foreach ($byType as $type => $ids) {
            $links = ChatTaskLink::query()
                ->where('message_type', $type)
                ->whereIn('message_id', array_values(array_unique($ids)))
                ->with([
                    'task:id,title,board_column_id,client_id,task_board_id',
                    'task.client:id,name',
                    'task.taskBoard.team:id,slug',
                    'task.column:id,name',
                ])
                ->get();

            foreach ($links as $link) {
                $task = $link->task;
                if (! $task) {
                    continue;
                }
                $cardsByKey[$type.':'.$link->message_id] = [
                    'id' => (int) $task->id,
                    'title' => (string) $task->title,
                    'team_slug' => $task->taskBoard?->team?->slug,
                    'board_column_id' => (int) $task->board_column_id,
                    'column_name' => $task->column?->name,
                    'client_name' => $task->client?->name,
                ];
            }
        }

        if ($cardsByKey === []) {
            return $messages;
        }

        return $messages->map(function (array $row) use ($cardsByKey) {
            $type = $this->messageTypeForRow($row);
            $id = (int) ($row['id'] ?? 0);
            if ($type && $id > 0) {
                $card = $cardsByKey[$type.':'.$id] ?? null;
                if ($card) {
                    $row['task_card'] = $card;
                }
            }

            return $row;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function messageTypeForRow(array $row): ?string
    {
        if (($row['kind'] ?? null) === 'call') {
            return null;
        }

        return TeamChatMessage::class;
    }

    /**
     * @param  Collection<int, TeamChatMessage|PrivateChatMessage|DirectMessage|array<string, mixed>>  $messages
     * @param  class-string  $modelClass
     * @return Collection<int, array<string, mixed>>
     */
    public function enrichForModel(Collection $messages, string $modelClass): Collection
    {
        $mapped = $messages->map(function ($msg) use ($modelClass) {
            if (is_array($msg)) {
                return $msg;
            }

            return match ($modelClass) {
                PrivateChatMessage::class => $msg->toChatArray(),
                DirectMessage::class => $msg->toChatArray(),
                default => $msg->toChatArray(),
            };
        });

        $enriched = $this->enrichWithExplicitType($mapped, $modelClass);

        return $enriched;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $messages
     * @return Collection<int, array<string, mixed>>
     */
    private function enrichWithExplicitType(Collection $messages, string $modelClass): Collection
    {
        if ($messages->isEmpty()) {
            return $messages;
        }

        $ids = $messages
            ->map(fn (array $row) => (int) ($row['id'] ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return $messages;
        }

        $cardsById = [];
        $links = ChatTaskLink::query()
            ->where('message_type', $modelClass)
            ->whereIn('message_id', $ids)
            ->with([
                'task:id,title,board_column_id,client_id,task_board_id',
                'task.client:id,name',
                'task.taskBoard.team:id,slug',
                'task.column:id,name',
            ])
            ->get();

        foreach ($links as $link) {
            $task = $link->task;
            if (! $task) {
                continue;
            }
            $cardsById[(int) $link->message_id] = [
                'id' => (int) $task->id,
                'title' => (string) $task->title,
                'team_slug' => $task->taskBoard?->team?->slug,
                'board_column_id' => (int) $task->board_column_id,
                'column_name' => $task->column?->name,
                'client_name' => $task->client?->name,
            ];
        }

        return $messages->map(function (array $row) use ($cardsById) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0 && isset($cardsById[$id])) {
                $row['task_card'] = $cardsById[$id];
            }

            return $row;
        });
    }
}
