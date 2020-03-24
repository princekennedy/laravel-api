<?php namespace App\Libraries;

use App\UserSession;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserSessionHandler {


	public static function getLastSessionByUserId($userId) {

		try {
			
			$userSession = UserSession::where('user_id', '=', $userId)
										->orderBy('id', 'DESC')
										->firstOrFail();

			return $userSession;

		} catch ( ModelNotFoundException $e) {
			return false;
		}
	}


	public static function registerSession($user, \Illuminate\Http\Request $request) {
		$userSession = new UserSession;
		$userSession->user_id = $user->id;
		$userSession->ip_address = $request->ip();
		$userSession->save();
	}


	public static function mostRecentSessionsByUserId ($userId, $limit = 5) {

		$userSessions = UserSession::where('user_id', '=', $userId)
									->orderBy('id', 'DESC')
									->take($limit)
									->get();

		return $userSessions;
	}

}


?>