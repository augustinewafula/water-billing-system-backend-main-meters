<?php

namespace App\Actions;

use App\Models\UnreadMeter;

class DeleteUnreadMeter
{
    public function execute($id)
    {
        return UnreadMeter::findOrFail($id)->delete();
    }

}
