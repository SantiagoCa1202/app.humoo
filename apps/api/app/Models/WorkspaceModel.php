<?php

namespace App\Models;

abstract class WorkspaceModel extends BaseModel
{
  protected static function booted(): void
  {
    //
    // Más adelante agregaremos aquí el Tenant Scope,
    // pero NO confíes únicamente en global scopes
    // para seguridad.
    //
  }
}
