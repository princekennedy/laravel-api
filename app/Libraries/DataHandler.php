<?php namespace App\Libraries;

use App\Customer;
use App\Account;
use App\AccountDocument;
use App\AccountFile;
use App\AccountType;
use App\Entity;

use App\AccountModification;
use App\AccountDocumentModification;
use App\DocumentFileModification;
use App\User;
use App\Branch;
use Request;
use Auth;
use DB;


use App\Jobs\ProcessPersonalAccount;
use App\Jobs\ProcessGroupAccount;
use App\Jobs\ProcessBusinessAccount;

use App\Libraries\AccountFileHandler;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Libraries\UserActivityHandler;
use App\Libraries\EntityHandler;


class DataHandler {


    /**
    *Correct the names to first uppercase.
    *@return  string of the case corrected name.
    */
    public static function correctNameCasing($name) {
    	return ucfirst($name);
    }


    /**
    *Generate a summary report
    *@return  an eloquent object with records.
    */
    public static  function briefSummaryReport () { 

        $rawQuery1 = "(SELECT count(*) from accounts) as total_accounts";
        $rawQuery2 = "(SELECT count(*) from customers) as total_customers";
        $rawQuery3 = "(SELECT count(*) from documents) as total_documents";
        $rawQuery4 = "(SELECT count(*) from document_files) as total_files";
        $rawQuery5 = "(SELECT sum(pages) from document_files) as total_pages";

        $record = Entity::select(
            DB::raw($rawQuery1),
            DB::raw($rawQuery2),
            DB::raw($rawQuery3),
            DB::raw($rawQuery4),
            DB::raw($rawQuery5)
        )->first();

        return $record;
    }

    /**
    *Generate a summary report
    *@return  an eloquent object with records.
    */
    public static  function summaryReport () { 

    	$rawQuery1 = "(SELECT count(*) from accounts) as total_accounts";
    	$rawQuery2 = "(SELECT count(*) from customers) as total_customers";
    	$rawQuery3 = "(SELECT count(*) from documents) as total_documents";
    	$rawQuery4 = "(SELECT count(*) from document_files) as total_files";

    	$record = Entity::select(
    		DB::raw($rawQuery1),
    		DB::raw($rawQuery2),
    		DB::raw($rawQuery3),
    		DB::raw($rawQuery4)
    	)->first();

    	return $record;
    }


    /**
    *Generate a summary report
    *@return  an eloquent object with records.
    */
    public static  function filterSummaryReport () { 

        $fromDate = Request::input('from_date');
        $toDate = Request::input('to_date');

        if ($fromDate) {
             $fromDate =  $fromDate . ' 00:00';
        }else {
             $fromDate =  '1900-01-01 00:00';
        }

        if ($toDate) {
            $toDate = Request::input('to_date') . ' 23:59';
        } else {
            $toDate = date('Y-m-d 23:59');

        }

        $rawQuery1 = "(SELECT count(*) from accounts where created_at >= '$fromDate' AND created_at <= '$toDate') as total_accounts";


        $rawQuery2 = "(SELECT count(*) from customers where created_at >= '$fromDate' AND created_at <= '$toDate') as total_customers";
        $rawQuery3 = "(SELECT count(*) from documents  where created_at >= '$fromDate' AND created_at <= '$toDate') as total_documents";
        $rawQuery4 = "(SELECT count(*) from document_files  where created_at >= '$fromDate' AND created_at <= '$toDate') as total_files";
        $rawQuery5 = "(SELECT sum(pages) from document_files  where created_at >= '$fromDate' AND created_at <= '$toDate') as total_pages";

        $record = Entity::select(
            DB::raw($rawQuery1),
            DB::raw($rawQuery2),
            DB::raw($rawQuery3),
            DB::raw($rawQuery4),
            DB::raw($rawQuery5)
        )->first();

        return $record;
    }







}










?>
















