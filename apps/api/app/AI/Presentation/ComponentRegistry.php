<?php

namespace App\AI\Presentation;

class ComponentRegistry
{
  public const COMPONENTS = [
    'clarification.options@1',

    'events.list@1',
    'events.summary@1',

    'prep.list@1',
    'prep.preview@1',
    'prep.weekly-board@1',

    'action.confirm@1',
    'action.result@1',

    'tasks.mine@1',

    'inventory.missing@1',

    'error.recovery@1',
  ];

  public static function supports(
    string $key
  ): bool {
    return in_array(
      $key,
      self::COMPONENTS,
      true
    );
  }
}
