<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Report;
use Inertia\Inertia;

// Ссылка для клиента: те же страницы проекта и отчёта, но без входа и только для просмотра.
// Доступ даёт секретный share_token проекта; кнопки правки, выгрузки и синхронизации скрыты на фронте,
// а сами их маршруты остаются за логином.
class ShareController extends Controller
{
    public function project(string $token, ProjectController $projects)
    {
        $project = Project::where('share_token', $token)->firstOrFail();

        return Inertia::render('Projects/Show', $projects->props($project) + ['share' => ['token' => $token]]);
    }

    public function report(string $token, Report $report, ReportController $reports)
    {
        $project = Project::where('share_token', $token)->firstOrFail();
        abort_unless($report->project_id === $project->id, 404);

        return Inertia::render('Reports/Show', $reports->props($report) + ['share' => ['token' => $token]]);
    }
}
