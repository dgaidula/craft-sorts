<?php

namespace iceboxind\sorts\events;

use iceboxind\sorts\engine\RuleInterface;
use yii\base\Event;

/**
 * Fired once, when the engine is first built in a request, so a module or plugin
 * can add its own typography rules (Pro).
 *
 * ```php
 * Event::on(Plugin::class, Plugin::EVENT_REGISTER_RULES, function (RegisterRulesEvent $event) {
 *     $event->rules[] = new MyRule();
 * });
 * ```
 */
class RegisterRulesEvent extends Event
{
    /** @var array<int, mixed> the rules to add, each a {@see RuleInterface} with a new handle; anything else is logged and skipped */
    public array $rules = [];
}
