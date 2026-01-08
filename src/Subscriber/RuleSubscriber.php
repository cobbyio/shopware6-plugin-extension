<?php

declare(strict_types=1);

namespace CobbyPlugin\Subscriber;

use CobbyPlugin\CobbyPlugin;

class RuleSubscriber extends SimpleEntitySubscriber
{
    protected function getEntityType(): string
    {
        return 'rule';
    }

    protected function getConfigKey(): string
    {
        return CobbyPlugin::CONFIG_PREFIX.'enableRuleEvents';
    }
}
