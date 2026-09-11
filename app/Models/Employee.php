<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Employee extends Model
{
    protected $fillable = ['name', 'position', 'photo'];

    // проекты сотрудника — те, где он указан ответственным (сравниваем имена без учёта регистра и пробелов)
    public function projects(): Collection
    {
        $name = mb_strtolower(trim($this->name));

        return Project::orderBy('name')->get()
            ->filter(fn ($p) => mb_strtolower(trim((string) $p->manager)) === $name)
            ->values();
    }
}
