<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Un réglage modifiable depuis l'administration (voir App\Services\Settings\SiteSettings). */
#[Fillable(['key', 'value', 'updated_by'])]
class Setting extends Model {}
