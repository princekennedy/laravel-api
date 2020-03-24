<?php

namespace App\Libraries;
use DB;

/*
	*
 	* 
*/
class PaginationHandler
{

	public static function test(){
		
		$sqlRows = DB::table('access_permissions')->select();
		$sqlRows = self::getOr($sqlRows);
		$sqlData = (self::getLimit($sqlRows)) ? self::getLimit($sqlRows) : $sqlRows ;
		
		return [
				"results" => $sqlData->get(),
				"rows" => $sqlRows->count(),
				// "s" => request()->all()
			];
	}	

	public static function test2(){
		
		$sqlData = DB::table('customers')->select();
		$sqlData = self::getOr($sqlData);
		$sqlData = self::getLimit($sqlData);
		$sqlData = self::getOrderBy($sqlData);

		$sqlRows = DB::table('customers')->select();
		$sqlRows = self::getOr($sqlRows);
		return [
				"results" => $sqlData->get(),
				"rows" => $sqlRows->count(),
				"s" => request()->all()
			];
	}


	public static function getLimit($sql){
		if(request()->page && request()->npp){
			$page = request()->page;
			$npp = request()->npp;
			$skip = $page * $npp;
			$skip = ($skip == $npp ) ? " 0 " : $skip ;
			$sql = $sql->skip($skip)->limit($npp);
		}
		return $sql;
	}	

	public static function getOrderBy($sql){
		if(request()->searchOrderBy){
			$orderBy = json_decode(request()->searchOrderBy ,true);
			foreach ($orderBy as $key => $value) {
				$sql = $sql->orderBy($key ,$value);
			}
		}
		return $sql;
	}

	public static function getOr($sql){
		if(request()->searchLike || request()->searchOr){
			$searchLike = json_decode(request()->searchLike ,true);
			$searchOr = json_decode(request()->searchOr ,true);
			if($searchLike){
				foreach ($searchLike as $key => $value) {
					$sql = $sql->where($key ,'LIKE','%%' . $value . '%%');
				}	
			}
			if($searchOr){
				$sql = $sql->where(function ($query) use ($searchOr) {
					foreach ($searchOr as $key => $value) {
						$query->orWhere($key ,'LIKE','%%' . $value . '%%');
					}
				});
			}
		// 	echo "string";
		}
		return $sql;
	}

}