<?php
declare(strict_types=1);

namespace SlimPostgres\BaseMVC\View;

use It_All\FormFormer\Form;

interface Forms
{
    public static function getFieldNames(): array;
    public function getForm(): Form;
}
