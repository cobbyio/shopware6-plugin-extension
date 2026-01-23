<?php

declare(strict_types=1);

namespace CobbyPlugin\Subscriber;

use CobbyPlugin\CobbyPlugin;

class MediaSubscriber extends SimpleEntitySubscriber
{
    protected function getEntityType(): string
    {
        return 'media';
    }

    protected function getConfigKey(): string
    {
        return CobbyPlugin::CONFIG_PREFIX . 'enableMediaEvents';
    }
}
