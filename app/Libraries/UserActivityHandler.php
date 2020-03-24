<?php namespace App\Libraries;


use App\ActivityType;
use App\UserActivity;
use App\UserSession;
use App\Libraries\UserSessionHandler;
use Request;

class UserActivityHandler {

	const ACTIVITY_TYPE_ID_CREATE = 1;
	const ACTIVITY_TYPE_ID_VIEW = 2;
	const ACTIVITY_TYPE_ID_DOWNLOAD = 3;
	const ACTIVITY_TYPE_ID_UPDATE = 4;
	const ACTIVITY_TYPE_ID_DISABLE = 5;
	const ACTIVITY_TYPE_ID_AUTHORIZE = 6;
	const ACTIVITY_TYPE_ID_UNAUTHORIZE = 7;



    /**
     *Most recent activities by entity type and primary value
     *@param $entityId : ID of the entity. Entity could be customer, account, user etc.
     *@param $primaryValue : value of the primary key.  Could be userId, customer number, account number etc
     *@param $activity type id : ID of the activity type. Activity type could be; create, read, delete etc
     *@param $limit : Maximum number of records to be fetched.
     *@param $entityReferenceField : name of the field being referenced. eg account number, username etc.
     *@return Activity object with user activities
     */
	public static function pushActivity (

		$entityId, 
		$entityPrimaryValue,
		$activityTypeId, 
		$entityReferenceField, 
		$entityReferenceValue ) {

		$userId = \Auth::user()->id;

		$lastSession = UserSessionHandler::getLastSessionByUserId($userId); 

		$sessionId = ($lastSession)? $lastSession->id : 0;

		$data = [
			'user_id' => $userId,
			'user_session_id' => $sessionId,
			'entity_id' => $entityId,
			'entity_primary_value' => $entityPrimaryValue,
			'activity_type_id' => $activityTypeId,
			'entity_reference_field' => $entityReferenceField,
			'entity_reference_value' =>$entityReferenceValue
		];

		UserActivity::updateOrCreate($data);
	}


    /**
     *Get the most recent activities
     *@param $limit : Maximum number of records to be fetched.
     *@return Activity object with activities
     */
	public static function mostRecentActivities($limit = 5) {

		// $sql = "SELECT 
		// 			users.first_name, 
		// 			users.last_name,
		// 			user_activities.user_id,
		// 			entities.name as entity_name,
		// 			user_activities.entity_reference_field,
		// 			user_activities.entity_reference_value,
		// 			user_sessions.ip_address,
		// 			activity_type_id,
		// 			entity_id,
		// 			entity_primary_value,
		// 			user_activities.created_at
		// 		FROM 
		// 		(((user_activities
		// 		   INNER JOIN users ON user_activities.user_id = users.id)
		// 		   INNER JOIN entities ON user_activities.entity_id = entities.id)
		// 		   INNER JOIN user_sessions ON user_activities.user_session_id = user_sessions.id)
		// 		ORDER BY user_activities.id DESC
		// 		LIMIT 10";
		$userActivity = UserActivity::join('users', 'user_activities.user_id', 'users.id')
			 ->join('entities', 'user_activities.entity_id', 'entities.id')
			 ->join('user_sessions', 'user_activities.user_session_id', 'user_sessions.id')

			 ->select(
				'users.first_name', 
				'users.last_name',
				'user_activities.user_id',
				'entities.name as entity_name',
				'user_activities.entity_reference_field',
				'user_activities.entity_reference_value',
				'user_sessions.ip_address',
				'activity_type_id',
				'entity_id',
				'entity_primary_value',
				'user_activities.created_at'
			)
			 // ->orderBy('user_activities.id', 'DESC')
			 ->take($limit)
			 ->get();
		return $userActivity;
	}



    /**
     *Get the most recent activities of a user
     *@param $userId : ID of the user with activities
     *@param $limit : Maximum number of records to be fetched.
     *@return Activity object with user activities
     */
	public static function mostRecentActivitiesByUserId($userId, $limit = 5) {


		$userActivity = UserActivity::join('users', 'user_activities.user_id', 'users.id')
			 ->join('entities', 'user_activities.entity_id', 'entities.id')
			 ->join('user_sessions', 'user_activities.user_session_id', 'user_sessions.id')

			 ->select(

				'users.first_name', 
				'users.last_name',
				'user_activities.user_id',
				'entities.name as entity_name',
				'user_activities.entity_reference_field',
				'user_activities.entity_reference_value',
				'user_sessions.ip_address',
				'activity_type_id',
				'entity_id',
				'entity_primary_value',
				'user_activities.created_at'

			)->where('users.id', '=', $userId)
			 // ->orderBy('user_activities.id', 'DESC')
			 ->take($limit)
			 ->get();
			 
		return $userActivity;
	}



    /**
     *Most recent activities by entity type and primary value
     *@param $entityId : ID of the entity. Entity could be customer, account, user etc.
     *@param $primaryValue : value of the primary key.  Could be userId, customer number, account number etc
     *@param $limit : Maximum number of records to be fetched.
     *@return Activity object with user activities
     */
	public static function mostRecentActivitiesByEntityIdAndPrimaryValue($entityId, $primaryValue, $limit = 5) {


		$userActivity = UserActivity::join('users', 'user_activities.user_id', 'users.id')
			 ->join('entities', 'user_activities.entity_id', 'entities.id')
			 ->join('user_sessions', 'user_activities.user_session_id', 'user_sessions.id')

			 ->select(

				'users.first_name', 
				'users.last_name',
				'user_activities.user_id',
				'entities.name as entity_name',
				'user_activities.entity_reference_field',
				'user_activities.entity_reference_value',
				'user_sessions.ip_address',
				'activity_type_id',
				'entity_id',
				'entity_primary_value',
				'user_activities.created_at'

			)
			 ->where('entities.id', '=', $entityId)
			 ->where('entity_primary_value', '=', $primaryValue)
			 // ->orderBy('user_activities.id', 'DESC')
			 ->take($limit)
			 ->get();

		return $userActivity;
	}


    /**
     *Most recent activities by entity type and primary value
     *@param $userId : ID of the user with activities
     *@param $limit : Maximum number of records to be fetched.
     *@return Activity object with user activities
     */
	public static function filterActivities () {

		$entityId = Request::input('entity');
		$activityTypeId = Request::input('activity_type');
		$entityReference = Request::input('entity_reference');
		$fromDate  = Request::input('from_date');
		$toDate  = Request::input('to_date');
		$username = Request::input('username');
		$ipAddress  = Request::input('ip_address');
		$primaryValue  = Request::input('primary_id');

		$userActivity = UserActivity::join('users', 'user_activities.user_id', 'users.id')
			 ->join('entities', 'user_activities.entity_id', 'entities.id')
			 ->join('user_sessions', 'user_activities.user_session_id', 'user_sessions.id')

			 ->select(

				'users.first_name', 
				'users.last_name',
				'user_activities.user_id',
				'entities.name as entity_name',
				'user_activities.entity_reference_field',
				'user_activities.entity_reference_value',
				'user_sessions.ip_address',
				'activity_type_id',
				'entity_id',
				'entity_primary_value',
				'user_activities.created_at'

			);

			 if ($entityId) {
			 	$userActivity = $userActivity->where('entity_id', '=', $entityId);
			 }
			 
			 if ($activityTypeId) {
			 	$userActivity = $userActivity->where('activity_type_id', '=', $activityTypeId);
			 }	

			 if ($entityReference) {
			 	$userActivity = $userActivity->where('entity_reference_value', 'LIKE', "%%$entityReference%%");
			 }

			 if ($fromDate) {
			 	$datetime = "$fromDate 00:00:00";
			 	$userActivity = $userActivity->where('user_activities.created_at', '>=', $datetime);
			 }

			 if ($toDate) {
			 	$datetime = "$toDate 23:59:59";
			 	$userActivity = $userActivity->where('user_activities.created_at', '<=', $datetime);
			 }
			 

			 if ($ipAddress) {
			 	$userActivity = $userActivity->where('user_sessions.ip_address', '=', $ipAddress);
			 }			 

			 if ($username) {
			 	$userActivity = $userActivity->where('users.username', '=', $username);
			 }

			 if ($primaryValue) {
			 	$userActivity = $userActivity->where('entity_primary_value', '=', $primaryValue);
			 }

			 if(	 	
			 	!$entityId &&
			 	!$activityTypeId &&
			 	!$entityReference &&
			 	!$fromDate &&
			 	!$toDate &&
			 	!$ipAddress &&
			 	!$username &&
			 	!$primaryValue
			 ){
			 	$userActivity = $userActivity->where('user_activities.id' ,"<" ,200);
			 }

			 $userActivity = $userActivity->orderBy('user_activities.id', 'DESC');
			 $userActivity = $userActivity->paginate(20);


		return $userActivity;

	}

    /**
     *Get all activity types
     *@return Activity object with activity types
     */
	public static function getActivityTypes () {
		return ActivityType::all();//ActivityType::find([1,2,3,4,5,6,7]); //ActivityType::all();
	}



    /**
     *Determine type of action using activity type.
     *Generate a user friendly verb of the activity 
     *@return the verb created.
     */
	public static function determineActivityPastActionByTypeId($activityTypeId) {

	    $action = "";

		switch ($activityTypeId) {
			case self::ACTIVITY_TYPE_ID_CREATE:
				$action = 'created';
				break;
			case self::ACTIVITY_TYPE_ID_VIEW:
				$action = 'viewed';
				break;
			case self::ACTIVITY_TYPE_ID_DOWNLOAD:
				$action = 'downloaded';
				break;
			case self::ACTIVITY_TYPE_ID_UPDATE:
				$action = 'updated';
				break;
			case self::ACTIVITY_TYPE_ID_DISABLE:
				$action = 'disabled';
				break;
			
			case self::ACTIVITY_TYPE_ID_AUTHORIZE:
				$action = 'authorized';
				break;
			
			case self::ACTIVITY_TYPE_ID_UNAUTHORIZE:
				$action = 'unauthorized';
				break;
			
			default:
				$action = "did something with";
				break;
		}

		return $action;
	}







}


?>