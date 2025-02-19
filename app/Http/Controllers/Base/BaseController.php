<?php

namespace App\Http\Controllers\Base;

use Illuminate\View\View;
use Illuminate\Http\Response;
use App\Traits\Base\BaseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class BaseController extends Controller
{
    use BaseTrait;

    /**
     * Prepare the output for the response.
     *
     * @param mixed $output
     * @param string $status
     * @return JsonResponse|view
     */
    protected function prepareOutput($output, string $status = Response::HTTP_OK): JsonResponse|View|RedirectResponse
    {
        if ($output instanceof View) {
            return $output;
        }

        if ($output instanceof RedirectResponse) {
            return $output;
        }

        return response()->json($output, $status);
    }
}
