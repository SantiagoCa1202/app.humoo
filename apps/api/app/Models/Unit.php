<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends BaseModel
{
  public function recipeIngredients(): HasMany
  {
    return $this->hasMany(RecipeIngredient::class);
  }

  public function recipeYields(): HasMany
  {
    return $this->hasMany(RecipeYield::class);
  }
}
