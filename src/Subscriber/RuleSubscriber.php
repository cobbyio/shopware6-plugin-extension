<?php declare(strict_types=1);

namespace CobbyShopware6Extension\Subscriber;

use CobbyShopware6Extension\CobbyShopware6Extension;

class RuleSubscriber extends SimpleEntitySubscriber
{
    protected function getEntityType(): string
    {
        return 'rule';
    }

    protected function getConfigKey(): string
    {
        return CobbyShopware6Extension::CONFIG_PREFIX . 'enableRuleEvents';
    }
}
