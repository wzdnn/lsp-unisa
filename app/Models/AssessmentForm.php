<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AssessmentForm extends Model
{
    protected $table = 'lsp_assessment_forms';
    protected $guarded = ['id'];

    public function versions() { return $this->hasMany(AssessmentFormVersion::class, 'form_id'); }
    public function scheme(): BelongsTo { return $this->belongsTo(LspSkema::class, 'kdlsp_skema', 'kdlsp_skema'); }
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(PtUnitKerja::class, 'lsp_assessment_form_prodi', 'form_id', 'kdunitkerja', 'id', 'kdunitkerja')->withTimestamps();
    }
}
