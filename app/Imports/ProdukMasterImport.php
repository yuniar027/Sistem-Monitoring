<?php

namespace App\Imports;

use App\Models\ProdukMaster;
use Maatwebsite\Excel\Concerns\ToModel;

class ProdukMasterImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new ProdukMaster([
            //
        ]);
    }
}
