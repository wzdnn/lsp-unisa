<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentForm extends Model
{
    protected $table = 'lsp_assessment_forms';
    protected $guarded = ['id'];

    public function versions() { return $this->hasMany(AssessmentFormVersion::class, 'form_id'); }
}
