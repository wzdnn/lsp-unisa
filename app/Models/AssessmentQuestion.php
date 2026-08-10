<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AssessmentQuestion extends Model
{
    protected $table = 'lsp_assessment_questions';
    protected $guarded = ['id'];
    protected $casts = ['is_required' => 'boolean', 'options' => 'array', 'settings' => 'array'];

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(LspSkemaUnitKompetensi::class, 'lsp_assessment_question_units', 'question_id', 'kdlsp_skema_unitkompetensi')
            ->withPivot(['kdlsp_skema_unitkompetensi_elemen', 'kdlsp_skema_unitkompetensi_elemen_kriteria'])->withTimestamps();
    }
}
