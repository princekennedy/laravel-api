<?php 
namespace App\Libraries;

use App\User;
use App\AccessLevel;
use Request;
use Auth;
use DB;

use App\AccessPermission;

class AccessLevelHandler {


	const ACCESS_PERMISSION_ID_CREATE_USER  = 1;
	const ACCESS_PERMISSION_ID_UPDATE_USER  = 2;
	const ACCESS_PERMISSION_ID_VIEW_USERS  = 3;
	const ACCESS_PERMISSION_ID_AUTHORIZE_USER_MODIFICATION = 4;
	const ACCESS_PERMISSION_ID_AUDIT_TRAIL  = 5;
	const ACCESS_PERMISSION_ID_PULL_REPORTS = 6;

	const ACCESS_LEVEL_ID_SUPER_USER = 1;
	const ACCESS_LEVEL_ID_ADMIN = 2;
	const ACCESS_LEVEL_ID_STAFF = 3;
	const ACCESS_LEVEL_ID_TECH = 4;



	/**
	*CHECK IF AUTHENTICATED USER HAS ACCESS AND THE EXECUTE
	*@param permissions : Permissions to be checked against user permission.
	*@param callback : a callback function to be invoked if the user has permissions.
	*/
	public static function checkAccessExec(Array $permissions, $callback) {

		$user = Auth::user();

		$result = User::join('user_access_permission', 'users.id', 'user_access_permission.user_id')
						->where('users.id', '=', $user->id)
						->whereIn('user_access_permission.access_permission_id', $permissions)
						->get();

		if($result->count()) {
			return is_callable($callback)? $callback() : '';
		}

		$errorMessage = "You do not have enough permissions to access the section you were trying to.";
		session()->flash('feedback-error' ,$errorMessage);
		switch ($user->access_level_id) {
			case self::ACCESS_LEVEL_ID_SUPER_USER || 
				 self::ACCESS_LEVEL_ID_ADMIN || 
				 self::ACCESS_LEVEL_ID_STAFF || 
				 self::ACCESS_LEVEL_ID_TECH :

				return \Redirect::to('/');//->with("feedback-error", $errorMessage);

				break;
			default:
				return \Redirect::to('login');//->with("feedback-error", $errorMessage);

				break;
		}
	}

	/**
	*CHECK IF AUTHENTICATED USER HAS ACCESS AND THE EXECUTE
	*@param permissions : Permissions to be checked against user permission.
	*@param callback : a callback function to be invoked if the user has permissions.
	*/
	public static function userCheckAccessExec($user, Array $permissions, $callback) {

		$result = User::join('user_access_permission', 'users.id', 'user_access_permission.user_id')
						->where('users.id', '=', $user->id)
						->whereIn('user_access_permission.access_permission_id', $permissions)
						->get();		

		if($result->count()) {
			return is_callable($callback)? $callback() : '';
		}

		$errorMessage = "You do not have enough permissions to access the section you were trying to.";
		session()->flash('feedback-error' ,$errorMessage);

		switch ($user->access_level_id) {
			case self::ACCESS_LEVEL_ID_SUPER_USER || 
				 self::ACCESS_LEVEL_ID_ADMIN || 
				 self::ACCESS_LEVEL_ID_STAFF || 
				 self::ACCESS_LEVEL_ID_TECH :
				return \Redirect::to('/');//->with("feedback-error", $errorMessage);

				break;
			default:
				return \Redirect::to('login');//->with("feedback-error", $errorMessage);

				break;
		}

	}

	/**
	*GET ACCESS LEVELS BY ARRAY OF IDS
	*@param IDs, array of IDS to be collected.
	*@return object with access levels
	*/
   public static function getAccessLevelsByIds (array $ids) {

        return AccessLevel::select()
                            ->whereIn("id", $ids)
                            ->with('permissions')
                            ->get();
    }











}










?>