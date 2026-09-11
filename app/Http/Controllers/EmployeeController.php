<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = Employee::orderBy('name')->get()->map(fn (Employee $e) => [
            'id' => $e->id,
            'name' => $e->name,
            'position' => $e->position,
            'photo' => $e->photo,
            // проекты подтягиваются по полю «Ответственный» в проекте
            'projects' => $e->projects()->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'color' => $p->color,
                'is_active' => $p->is_active,
            ])->all(),
        ]);

        return Inertia::render('Employees/Index', ['employees' => $employees]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Employee::create($data);

        return back()->with('success', 'Сотрудник добавлен');
    }

    public function update(Request $request, Employee $employee)
    {
        $employee->update($this->validated($request));

        return back()->with('success', 'Сотрудник обновлён');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return back()->with('success', 'Сотрудник удалён');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'photo' => 'nullable|string|max:255',
        ]);

        return [
            'name' => trim($data['name']),
            'position' => trim($data['position'] ?? '') ?: null,
            'photo' => $data['photo'] ?? null,
        ];
    }
}
