<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::withCount('reports')
            ->with(['reports' => fn ($q) => $q->with('weeklyStats')])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(function (Project $p) {
                $last = $p->reports->first();

                return [
                    'id'            => $p->id,
                    'name'          => $p->name,
                    'color'         => $p->color,
                    'reports_count' => $p->reports_count,
                    'last_period'   => $last?->period_label,
                    'last_totals'   => $last?->totals,
                    'is_active' => $p->is_active,
                ];
            });

        return Inertia::render('Projects/Index', ['projects' => $projects]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'color' => 'nullable|string|max:9',
            'client_period' => 'nullable|string|max:255',
            'manager' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $project = Project::create([
            'name'  => $data['name'],
            'slug'  => Str::slug($data['name']).'-'.Str::lower(Str::random(5)),
            'color' => $data['color'] ?? '#6C4CF0',
            'client_period'   => $data['client_period'] ?? null,
            'manager'         => $data['manager'] ?? null,
            'is_active'       => $data['is_active'] ?? true,
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Проект создан');
    }

    public function show(Project $project)
    {
        $project->load(['reports.weeklyStats']);

        $reports = $project->reports->map(fn ($report) => [
            'id'           => $report->id,
            'year'         => $report->year,
            'month'        => $report->month,
            'period_label' => $report->period_label,
            'totals'       => $report->totals,
        ]);

        return Inertia::render('Projects/Show', [
            'project' => [
                'id'    => $project->id,
                'name'  => $project->name,
                'color' => $project->color,
                'client_period' => $project->client_period,
                'manager' => $project->manager,
                'is_active' => $project->is_active,
            ],
            'reports' => $reports,
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'color'         => 'nullable|string|max:9',
            'client_period' => 'nullable|string|max:255',
            'manager'       => 'nullable|string|max:255',
            'is_active'     => 'boolean',
        ]);

        $project->update([
            'name'          => $data['name'],
            'color'         => $data['color'] ?? '#6C4CF0',
            'client_period' => $data['client_period'] ?? null,
            'manager'       => $data['manager'] ?? null,
            'is_active'     => $data['is_active'] ?? false,
        ]);

        return back()->with('success', 'Проект обновлён');
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Проект удалён');
    }
}
