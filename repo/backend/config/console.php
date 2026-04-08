<?php

return [
    'commands' => [
        \app\command\OrderExpireCommand::class,
        \app\command\GovernanceDedupCommand::class,
        \app\command\GovernanceFillCommand::class,
        \app\command\GovernanceLineageCommand::class,
        \app\command\GovernanceQualityCommand::class,
        \app\command\CredibilityRecomputeCommand::class,
        \app\command\SearchBuildDictionaryCommand::class,
    ],
];
