<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollResource;
use App\Models\User;
use App\Services\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function me(Request $request, PayrollCalculator $calculator)
    {
        $user = $request->user();

        if ($user->role !== UserRole::Enseignant) {
            abort(403);
        }

        return new PayrollResource($calculator->forTeacher($user, ...$this->range($request)));
    }

    /**
     * Vue admin : salaire de n'importe quel enseignant (suivi_salaires.php /
     * suiviHeurProf.php de l'ancienne app).
     */
    public function forTeacher(Request $request, User $teacher, PayrollCalculator $calculator)
    {
        abort_unless($teacher->role === UserRole::Enseignant, 404);

        return new PayrollResource($calculator->forTeacher($teacher, ...$this->range($request)));
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        return [
            isset($data['from']) ? Carbon::parse($data['from']) : null,
            isset($data['to']) ? Carbon::parse($data['to']) : null,
        ];
    }
}
