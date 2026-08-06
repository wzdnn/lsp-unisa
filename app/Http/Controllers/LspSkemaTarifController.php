<?php

namespace App\Http\Controllers;

use App\Models\LspSkemaTarif;
use Illuminate\Http\Request;

class LspSkemaTarifController extends Controller
{
    //
    public function index()
    {
        return response()->json(LspSkemaTarif::with('skema')->get());
    }

    public function show($kdlsp_skema)
    {
        return response()->json(
            LspSkemaTarif::where('kdlsp_skema', $kdlsp_skema)->first()
        );
    }

    // 1 skema = 1 nominal, upsert
    public function store(Request $request)
    {
        $this->authorizeAdminRole();

        $validated = $request->validate([
            'kdlsp_skema' => 'required|exists:lsp_skema,kdlsp_skema',
            'nominal' => 'required|integer|min:0',
        ]);

        $tarif = LspSkemaTarif::updateOrCreate(
            ['kdlsp_skema' => $validated['kdlsp_skema']],
            ['nominal' => $validated['nominal']]
        );

        return response()->json($tarif);
    }

    private function authorizeAdminRole()
    {
        if (!in_array(session('user.role'), ['admin', 'superadmin'])) {
            abort(403, 'Hanya admin yang dapat melakukan aksi ini');
        }
    }
}
