<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentFormVersion extends Model
{
    protected $table = 'lsp_assessment_form_versions';
    protected $guarded = ['id'];
    protected $casts = ['settings' => 'array', 'published_at' => 'datetime'];

    public function form() { return $this->belongsTo(AssessmentForm::class, 'form_id'); }
    public function sections() { return $this->hasMany(AssessmentSection::class, 'form_version_id')->orderBy('sort_order'); }
}
