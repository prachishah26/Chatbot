<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Chat\Llm\ModelSelectionService;
use App\Http\Requests\StoreModelSelectionRequest;
use Illuminate\Http\RedirectResponse;

final class ModelSelectionController extends Controller
{
    public function __construct(private readonly ModelSelectionService $modelSelection) {}

    /**
     * Switch the model used for subsequent replies.
     */
    public function store(StoreModelSelectionRequest $request): RedirectResponse
    {
        $this->modelSelection->choose($request->session(), $request->model());

        return back();
    }
}
