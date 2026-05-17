<?php

return [

    /** الجولات التفاعلية — معطّلة افتراضياً حتى استقرار الواجهة */
    'enabled' => filter_var(env('PRODUCT_TOURS_ENABLED', false), FILTER_VALIDATE_BOOL),

];
