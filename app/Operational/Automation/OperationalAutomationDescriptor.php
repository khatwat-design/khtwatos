<?php

namespace App\Operational\Automation;

/**
 * Immutable catalog row for internal automation registry / future control plane.
 *
 * @phpstan-type LegacyCatalogRow array{
 *     id: string,
 *     name_ar: string,
 *     trigger: string,
 *     code_paths: list<string>,
 *     notes: ?string,
 *     domain: string
 * }
 */
final readonly class OperationalAutomationDescriptor
{
    /**
     * @param  list<string>  $codePaths
     */
    public function __construct(
        public string $id,
        public string $nameAr,
        public string $trigger,
        public array $codePaths,
        public ?string $notes,
        public OperationalAutomationDomain $domain,
    ) {}

    /**
     * @return LegacyCatalogRow
     */
    public function toLegacyCatalogRow(): array
    {
        return [
            'id' => $this->id,
            'name_ar' => $this->nameAr,
            'trigger' => $this->trigger,
            'code_paths' => $this->codePaths,
            'notes' => $this->notes,
            'domain' => $this->domain->value,
        ];
    }
}
