<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentReview extends Model
{
    protected $table = 'lsp_assessment_reviews';
    protected $guarded = ['id'];
    protected $casts = ['reviewed_at' => 'datetime'];
}
