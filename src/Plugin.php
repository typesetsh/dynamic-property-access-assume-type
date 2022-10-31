<?php

declare(strict_types=1);

namespace Typesetsh\Psalm\DynamicPropertyAccessAssumeType;

use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use SimpleXMLElement;

class Plugin implements PluginEntryPointInterface
{
    public function __invoke(RegistrationInterface $registration, ?SimpleXMLElement $config = null): void
    {
        $this->registerHandlers($registration);
    }

    private function registerHandlers(RegistrationInterface $registration): void
    {
        require_once __DIR__.'/Handler/DynamicClass.php';
        $registration->registerHooksFromClass(Handler\DynamicClass::class);

        require_once __DIR__.'/Handler/AllowArrayCasting.php';
        $registration->registerHooksFromClass(Handler\AllowArrayCasting::class);
    }
}
