<?php namespace App\Libraries;

use Request;
use Hash;
use Auth;

use App\Account;
use App\Document;
use App\DocumentFile;
use App\AccountType;

use App\CustomerModification;
use App\AccountModification;
use App\DocumentModification;
use App\DocumentFileModification;

use App\ArchivingBox;
use App\User;
use App\UserModification;
use App\Branch;
use App\AccessLevel;

use App\Exceptions\ExistingUserException;
use App\Exceptions\UserCredentialsNotAuthenticException;
use App\Exceptions\NoUserArchivingBoxException;

use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Exceptions\PendingUserModificationException;


class UserHandler{


     const ACCESS_LEVEL_ID_SUPER_USER = 1;
     const ACCESS_LEVEL_ID_ADMIN = 2;
     const ACCESS_LEVEL_ID_STAFF = 3;
     const ACCESS_LEVEL_ID_TECH = 4;


     const CACHE_KEY_MOST_RECENT_USERS = "most_recent_users";

     const CACHE_KEY_USERS_WITH_HIGHEST_RECORDS = "users_with_highest_records";

     const  USER_MODIFICATION_PENDING_STATUS = 0;
     const  USER_MODIFICATION_AUTHORIZED_STATUS = 1;
     const  USER_MODIFICATION_UNAUTHORIZED_STATUS = 2;

     public static function generateUsersWithHighestRecordsCacheKey ($limit) {
            return self::CACHE_KEY_USERS_WITH_HIGHEST_RECORDS .'_'.$limit;
     }
    /**
     *Get most recent users
     *@param $limit : maximum amount of records to be returned
     *@return : Most recent users
     */
    public static function mostRecentUsers($limit = 5) {

        $cacheKey = self::generateMostRecentUsersCacheKey($limit);

        if (\Cache::has($cacheKey)) {
            return \Cache::get($cacheKey);
        }

        $rawQuery = "(select name from access_levels 
                    where access_levels.id = users.access_level_id) as access_level";

        $users = User::select(\DB::raw($rawQuery), 'users.*' )
                                ->orderBy('id', 'DESC')
                                ->take($limit)->get();

        \Cache::put($cacheKey, $users, 15);

        return $users;
    }


    /**
     *Create username exists for an existing user
     *the user should be available if the user already has it.
     *@param username : username of the user
     *@param $userId : ID of the user
     *@return : the new userModification created.
     */
    public static function usernameAlreadyExistsForUser($username, $userId) {
         $count =  User::where('username', '=', $username)
                     ->where('id', '!=', $userId)
                     ->count();

        return $count > 0 ? true : false;
    }

    /**
     *Create username exists for a new user
     *@param $username : the username to be checked
     *@return : the new userModification created.
     */
    public static function usernameAlreadyExists($username) {
        $count =  User::where('username', '=', $username)
                     ->count();

        return $count > 0 ? true : false;
    }

    /**
     *Check if user has a pending modification
     *@param $userId : ID of the actual user with podification
     *@return bool
     */
    public static function userHasPendingModification($userId) {
         $count =  UserModification::where('user_id', '=', $userId)
                     ->where('authorization_status', '=', 0)
                     ->count();

        return $count > 0 ? true : false;
    }


    /**
     *Update own account
     *Check password format
     */
    public static function secureUpdateOwnAccount() {

        $userId =  Auth::user()->id;
        $username = Request::input('username');
        $password = Request::input('password');
        $securityModification = false;

        if (self::usernameAlreadyExistsForUser($username, $userId)) {
            $message = "username $username is already taken by another user";
            throw new ExistingUserException($message);
        }

        $userId =  Auth::user()->id;
        $user   =  User::where('id', '=', $userId)->firstOrFail();

        if ($password) {
            $user->password   =  Hash::make($password);
            $securityModification = true;
        }

        $userModification =  self::createUserModificationFromUser($user, $securityModification);
        $user = self::updateUserFromModification($user, $userModification);

        UserActivityHandler::pushActivity( 
            EntityHandler::ENTITY_ID_USER,
             $user->id,
             UserActivityHandler::ACTIVITY_TYPE_ID_UPDATE,
             "username",
             $user->username  );

    }

    /**
     *Authorize user modification by modification id
     *@param $userModificationId : ID of the modification to be authorized
     */
    public static function  authorizeAccountModificationById ($userModificationId) {

        $userModification = UserModification::where('id', '=', $userModificationId)
                                            ->with('permissions')
                                            ->firstOrFail();

        $permissions = $userModification->permissions->pluck('id')->toArray();

        if($userModification->user_id) {

             $user = User::where('id', '=', $userModification->user_id)->firstOrFail();

             $user = self::updateChangeAuthorizationStatus($user, $userModification, true);

             if (count($permissions)) {
                $user->permissions()->detach();
                $user->permissions()->attach( $permissions);
             }
             
        } else {

            $username = $userModification->username;
            if (self::usernameAlreadyExists( $username )) {
                $message = "username $username is already taken by another user";
                $message .= " or pending update has already been verified";
                throw new ExistingUserException($message);
            }

            $user = UserHandler::createUserFromModification($userModification);
            $user->permissions()->attach($permissions);
        }

        UserActivityHandler::pushActivity( 
            EntityHandler::ENTITY_ID_USER_MODIFICATION,
             $user->id,
             UserActivityHandler::ACTIVITY_TYPE_ID_AUTHORIZE,
             "username",
              $user->username );

    }

    /**
     *Authorize user modification by modification id
     *@param $userModificationId : ID of the modification to be authorized
     */
    public static function  unauthorizeAccountModificationById ($userModificationId, $comment =  null) {

        $userModification = UserModification::where('id', '=', $userModificationId)
                                            ->firstOrFail();

        $user = User::where('id', '=', $userModification->user_id)->firstOrFail();
        $user = self::updateChangeAuthorizationStatus($user, $userModification, false, $comment);


        UserActivityHandler::pushActivity( 
            EntityHandler::ENTITY_ID_USER_MODIFICATION,
             $user->id,
             UserActivityHandler::ACTIVITY_TYPE_ID_UNAUTHORIZE,
             "username",
              $user->username );
    }






    /**
     *Create user modification for new user from request.
     *@return : the new userModification created.
     */
    public static function createUserModification () {

        $username =  Request::input('username');
        $password =  Request::input('password');
        $active =  Request::input('active')? 1 : 0;

        $userModification = new UserModification;
        $userModification->first_name =  Request::input('first_name');
        $userModification->last_name  =  Request::input('last_name');
        $userModification->username   =  $username;

        if ( strlen($password) >= 6) {
            $userModification->password   =  Hash::make($password);
            $userModification->security_modification  = 1;
        }
        
        $userModification->access_level_id = Request::input('access_level_id');
        $userModification->active  = $active;

        $userModification->initiator_id = Auth::user()->id;
        
        $userModification->save();

        return $userModification;
    }

    /**
     *Create user modification for existing user from request.
     *@param $user : the user to be modified.
     *@return : the new userModification created.
     */
    public static function createUserModificationForUser(\App\User $user, $authorizationStatus = false) {

        $username =  Request::input('username');
        $password =  Request::input('password');
        $active =  Request::input('active')? 1 : 0;

        $userModification = new UserModification;
        $userModification->user_id = $user->id;
        $userModification->first_name =  Request::input('first_name');
        $userModification->last_name  =  Request::input('last_name');
        $userModification->username   =  $username;

        if ( strlen($password) >= 6) {
            $userModification->password   =  Hash::make($password);
            $userModification->security_modification  = 1;
        } else {
            $userModification->password = $user->password;
        } 

        $userModification->authorization_status = ($authorizationStatus)? 1 : 0;

        $userModification->access_level_id = Request::input('access_level_id');
        $userModification->active  = $active;

        $userModification->initiator_id = Auth::user()->id;
        
        $userModification->save();

        return $userModification;

    }


    /**
     *Create user modification for user from request.
     *@param $user : the user to be modified.
     *@return : the new userModification created.
     */
    public static function createUserModificationFromUser (
        \App\User $user, 
        $securityModification = false, 
        $authorizationStatus = 0) {

        $userModification = new UserModification;


        $userModification->password   =  $user->password;
        $userModification->username =    $user->username;
        $userModification->first_name =  $user->first_name;
        $userModification->last_name  =  $user->last_name;
        $userModification->access_level_id = $user->access_level_id;
        $userModification->active     = $user->active;
        $userModification->authorization_status = $authorizationStatus;

        if ($securityModification) {
            $userModification->security_modification  = 1;
        } else {
            $userModification->security_modification  = 0;
        }

        $userModification->save();
        return $userModification;
    }


    /**
     *Create user from userModification object
     *@param $userModification : userModification Object
     *@return : the new user created.
     */
    public static function createUserFromModification(\App\UserModification $userModification) {

        $user = new User;
        $user->user_modification_id =  $userModification->id;
        $user->first_name =  $userModification->first_name;
        $user->last_name  =  $userModification->last_name;
        $user->username   =  $userModification->username;
        $user->password   =  $userModification->password;
        $user->access_level_id = $userModification->access_level_id;
        $user->active     = $userModification->active;
        $user->save();

        $userModification->authorization_status = 1;
        $userModification->verifier_id =  \Auth::user()->id;
        $userModification->save();

        UserActivityHandler::pushActivity( 
            EntityHandler::ENTITY_ID_USER_MODIFICATION,
             $user->id,
             UserActivityHandler::ACTIVITY_TYPE_ID_AUTHORIZE,
             "username",
              $user->username );

        return $user;
    }



    /**
     *Update user from modification
     *Extract data from user modification
     *@param $user : User object to be updated
     *@param $userModification : User object with the update
     *@return : the new updated user.
     */
    public static function updateUserFromModification(
        \App\User $user, 
        \App\UserModification $userModification) {

        $user->user_modification_id =  $userModification->id;
        $user->first_name =  $userModification->first_name;
        $user->last_name  =  $userModification->last_name;
        $user->username   =  $userModification->username;
        $user->password   =  $userModification->password;
        $user->access_level_id = $userModification->access_level_id;
        $user->active     = $userModification->active;

        if ( $userModification->security_modification == 1 ) {
            $user->password   =  $userModification->password;
        }

        $user->save();

        $userModification->verifier_id = Auth::user()->id;
        $userModification->authorization_status = 1;


        $userModification->save();

        return $user;
    }


    /**
     *Update user from modification and change authorization status
     *Extract data from user modification
     *@param $user : User object to be updated
     *@param $userModification : User object with the update
     *@return : the new updated user.
     */
    public static function updateChangeAuthorizationStatus(
        \App\User $user, 
        \App\UserModification $userModification, 
        bool $authorized = false, 
        $comment = null) {

        $user->user_modification_id =  $userModification->id;
        $user->first_name =  $userModification->first_name;
        $user->last_name  =  $userModification->last_name;
        $user->username   =  $userModification->username;
        $user->password   =  $userModification->password;
        $user->access_level_id = $userModification->access_level_id;
        $user->active     = $userModification->active;

        if ( $userModification->security_modification == 1 ) {
            $user->password   =  $userModification->password;
        }

        $user->save();

        $userModification->verifier_id = Auth::user()->id;
        if($comment) {
            $userModification->verifier_comment = $comment;
        }

        $userModification->authorization_status = ($authorized)? 1 : 2;

        $userModification->save();

        return $user;
    }




    /**
     *attempt to create a user
     */
    public static function attemptUserCreate() {

        $username =  Request::input('username');
      
        if (self::usernameAlreadyExists($username)) {
            $message = "username $username is already taken by another user";
            throw new ExistingUserException($message);
        }


        $permissions =  Request::input('access_permissions');

        $userModification  = self::createUserModification(true, 0);
        $user = self::createUserFromModification($userModification);

        if ( $permissions && count($permissions) ) {
            $userModification->permissions()->attach($permissions);
            $user->permissions()->attach($permissions);
        }

        UserActivityHandler::pushActivity( 
            EntityHandler::ENTITY_ID_USER,
             $user->id,
             UserActivityHandler::ACTIVITY_TYPE_ID_CREATE,
             "username",
             $userModification->username );
    }


    /**
     *attempt to update a user
     *@param $userId : id of the user to be modified
     */
    public static function attemptUserUpdate($userId) {

        $username =  Request::input('username');
        $active =  Request::input('active')? 1 : 0;

        if (self::usernameAlreadyExistsForUser($username, $userId) ) {
            $message = "username $username is already taken by another user";
            throw new ExistingUserException($message);
        }

       if (self::userHasPendingModification($userId)) {
            $message = "username $username is already taken by another user";
            throw new PendingUserModificationException($message);
        }

        $user = User::where('id', '=', $userId )->firstOrFail();

        $userModification = self::createUserModificationForUser($user);

        $userModification->user_id = $user->id;
        $userModification->save();
        

        $permissions = Request::input('access_permissions');

        // TO DO
        if($permissions){
            if ( count($permissions) ) {
                $userModification->permissions()->detach();
                $userModification->permissions()->attach($permissions);
            }            
        }


        UserActivityHandler::pushActivity( 
            EntityHandler::ENTITY_ID_USER,
             $user->id,
             UserActivityHandler::ACTIVITY_TYPE_ID_UPDATE,
             "username",
             $user->username  );
    }


    /**
     *get usermodification by ID
     *@param $userModificationId : id of the user modification
     *@return : userModificationObject
     */
    public static function userModificationById($userModificationId) {
        return  UserModification::where('id', '=', $userModificationId)
                                              ->with('permissions')
                                              ->firstOrFail();
    }




















    public static function generateMostRecentUsersCacheKey($limit){
        return self::CACHE_KEY_MOST_RECENT_USERS .'_'.$limit;
    }

     // public static function generateUsersWithHighestRecordsCacheKey ($limit) {
     //        return self::CACHE_KEY_USERS_WITH_HIGHEST_RECORDS .'_'.$limit;
     // }


     public static function usersPeformanceSummary() {

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

        $authorizationStatus = AccountHandler::AUTHORIZATION_STATUS_APPROVED;

        $query = "(select count(*) from account_modifications ";
        $query .= "where authorization_status = {$authorizationStatus} ";
        $query .= "and created_at >= '{$fromDate}' and  created_at <= '{$toDate}'  ";
        $query .= " and account_modifications.initiator_id = users.id ) as records";

        $users = User::select(
            'id', 'username', 'first_name', 'last_name',
            \DB::raw($query))
                     ->orderBy('records','DESC')
                      ->get();

        return $users;
     }





     public static function usersWithHighestApprovedEntriesThisWeek($limit=5) {

        $cacheKey = self::generateUsersWithHighestRecordsCacheKey($limit);

        if (\Cache::has($cacheKey)) {
            return \Cache::get($cacheKey);
        }

        $today = date('Y-m-d 23:59:00');
        $week = date("W", strtotime($today)); // get week
        $y =    date("Y", strtotime($today)); // get year

        $firstDate =  date('Y-m-d 00:00:00',strtotime($y."W".$week)); //first date 

        $authorizationStatus = AccountHandler::AUTHORIZATION_STATUS_APPROVED;

        $query = "(select count(*) from account_modifications ";
        $query .= "where authorization_status = {$authorizationStatus} ";
        $query .= "and created_at >= '{$firstDate}' and  created_at <= '{$today}'  ";
        $query .= " and account_modifications.initiator_id = users.id ) as records";

        $users = User::select(
            'id', 'username', 'first_name', 'last_name',
            \DB::raw($query))
                     ->orderBy('records','DESC')
                      ->limit($limit)
                      ->get();

        \Cache::put($cacheKey, $users, 1);

        return $users;
     }



     public static function mostRecentUserInitiatedAccountModifications($userId, $limit=5) {

        return CustomerModification::select( 'account_modifications.*', 
                    'customer_modifications.*',
                    'account_modifications.id as account_modification_id')

                    ->join('account_modifications', 
                           'account_modifications.customer_modification_id', 
                           'customer_modifications.id')
                    ->where('account_modifications.initiator_id', '=', $userId )
                    ->orderBy('account_modifications.id', 'DESC')
                    // ->with('customerNumberModifications')
                    ->take($limit)
                    ->get();
    }

    public static function userPendingEntriesToday($userId) {

        $cacheKey = "user-{$userId}-total-pending-entries-today";
        $today = date('Y-m-d');

        if (\Cache::has($cacheKey)) {
            return \Cache::get($cacheKey);
        }

        $authorizationStatus = AccountHandler::AUTHORIZATION_STATUS_PENDING;

        $rawQuery  = "(SELECT count(*) FROM account_modifications ";
        $rawQuery .= " WHERE account_modifications.authorization_status = {$authorizationStatus} ";
        $rawQuery .= " AND initiator_id = {$userId}  AND created_at > '{$today} 00:00:00' ";
        $rawQuery .= " AND created_at < '{$today} 23:59:59' ) as total";

        $total = User::select(
        \DB::raw($rawQuery)
        )->first()->total;

        \Cache::put($cacheKey, $total, 1);

        return $total;
    }



    public static function userAuthorizedEntriesToday($userId) {

        $cacheKey = "user-{$userId}-total-authorized-entries-today";
        
        $today = date('Y-m-d');

        if (\Cache::has($cacheKey)) {
            return \Cache::get($cacheKey);
        }

        $authorizationStatus = AccountHandler::AUTHORIZATION_STATUS_APPROVED;

        $rawQuery  = "(SELECT count(*) FROM account_modifications ";
        $rawQuery .= " WHERE account_modifications.authorization_status = {$authorizationStatus} ";
        $rawQuery .= " AND initiator_id = {$userId}  AND created_at > '{$today} 00:00:00' ";
        $rawQuery .= " AND created_at < '{$today} 23:59:59' ) as total";

        $total = User::select(
        \DB::raw($rawQuery)
        )->first()->total;

        \Cache::put($cacheKey, $total, 1);

        return $total;
    }



    public static function userUnauthorizedEntriesToday($userId) {

        $cacheKey = "user-{$userId}-total-rejected-entries-today";

        $today = date('Y-m-d');

        if (\Cache::has($cacheKey)) {
            return \Cache::get($cacheKey);
        }

        $authorizationStatus = AccountHandler::AUTHORIZATION_STATUS_REJECTED;

        $rawQuery  = "(SELECT count(*) FROM account_modifications ";
        $rawQuery .= " WHERE account_modifications.authorization_status = {$authorizationStatus} ";
        $rawQuery .= " AND initiator_id = {$userId}  AND created_at > '{$today} 00:00:00' ";
        $rawQuery .= " AND created_at < '{$today} 23:59:59' ) as total";

        $total = User::select(
        \DB::raw($rawQuery)
        )->first()->total;

        \Cache::put($cacheKey, $total, 1);

        return $total;
    }



    public static function userPendingEntriesThisMonth($userId) {

        $pendingEntriesToday = self::userPendingEntriesToday($userId);
        $pendingEntriesThisMonthBeforeToday = self::userPendingEntriesThisMonthBeforeToday($userId);
        return $pendingEntriesToday  +  $pendingEntriesThisMonthBeforeToday;
    }


    public static function userAuthorizedEntriesThisMonth($userId) {

        $authorizedEntriesToday = self::userAuthorizedEntriesToday($userId);
        $authorizedEntriesThisMonthBeforeToday = self::userAuthorizedEntriesThisMonthBeforeToday($userId);
        return $authorizedEntriesToday  +  $authorizedEntriesThisMonthBeforeToday;
    }

    public static function userUnauthorizedEntriesThisMonth($userId) {

        $unauthorizedEntriesToday = self::userAuthorizedEntriesToday($userId);
        $unauthorizedEntriesThisMonthBeforeToday = self::userUnauthorizedEntriesThisMonthBeforeToday($userId);
        return $unauthorizedEntriesToday + $unauthorizedEntriesThisMonthBeforeToday;
    }



    public static function userPendingEntriesThisMonthBeforeToday($userId) {

        $cacheKey = "user-{$userId}-pending-entries-this-month-before-today";
        $today = date('Y-m-d');

        if (\Cache::has($cacheKey)) {
            return \Cache::get($cacheKey);
        }

        $authorizationStatus = AccountHandler::AUTHORIZATION_STATUS_PENDING;

        $from = date("Y-m-01");
        $lastMonthDay =  date("t");
        $to = date("Y-m-{$lastMonthDay}");

        $subQuery  = "(SELECT count(*) FROM account_modifications WHERE initiator_id=users.id ";
        $subQuery .= " AND account_modifications.authorization_status = {$authorizationStatus}";
        $subQuery .= " AND created_at >= '$from' AND created_at <= '$to' ";
        $subQuery .= " AND created_at < '$today 00:00:00' AND created_at <= '$to' ) as total";

        $total = User::select("users.*", 
                        \DB::raw($subQuery))
                    ->where('users.id', '=', $userId)
                    ->first()->total;

        \Cache::put($cacheKey, $total, 30);

        return $total;
    }


    public static function userAuthorizedEntriesThisMonthBeforeToday($userId) {

        $cacheKey = "user-{$userId}-authorized-entries-this-month-before-today";
        $today = date('Y-m-d');

        if (\Cache::has($cacheKey)) {
            return \Cache::get($cacheKey);
        }

        $authorizationStatus = AccountHandler::AUTHORIZATION_STATUS_APPROVED;

        $from = date("Y-m-01");
        $lastMonthDay =  date("t");
        $to = date("Y-m-{$lastMonthDay}");

        $subQuery  = "(SELECT count(*) FROM account_modifications WHERE initiator_id=users.id ";
        $subQuery .= " AND account_modifications.authorization_status = {$authorizationStatus}";
        $subQuery .= " AND created_at >= '$from' AND created_at <= '$to' ";
        $subQuery .= " AND created_at < '$today 00:00:00' AND created_at <= '$to' ) as total";

        $total = User::select("users.*", 
                        \DB::raw($subQuery))
                    ->where('users.id', '=', $userId)
                    ->first()->total;

        \Cache::put($cacheKey, $total, 30);

        return $total;
    }


    public static function userUnauthorizedEntriesThisMonthBeforeToday($userId) {

        $cacheKey = "user-{$userId}-unauthorized-entries-this-month-before-today";
        $today = date('Y-m-d');

        if (\Cache::has($cacheKey)) {
            return \Cache::get($cacheKey);
        }

        $authorizationStatus = AccountHandler::AUTHORIZATION_STATUS_REJECTED;

        $from = date("Y-m-01");
        $lastMonthDay =  date("t");
        $to = date("Y-m-{$lastMonthDay}");

        $subQuery  = "(SELECT count(*) FROM account_modifications WHERE initiator_id=users.id ";
        $subQuery .= " AND account_modifications.authorization_status = {$authorizationStatus}";
        $subQuery .= " AND created_at >= '$from' AND created_at <= '$to' ";
        $subQuery .= " AND created_at < '$today 00:00:00' AND created_at <= '$to' ) as total";

        $total = User::select("users.*", 
                        \DB::raw($subQuery))
                    ->where('users.id', '=', $userId)
                    ->first()->total;

        \Cache::put($cacheKey, $total, 30);

        return $total;
    }



    public static function currentUserArchivingBox($userId) {

        $cacheKey = ArchivingBoxHandler::generateUserArchivingBoxCacheKey($userId);

        if (\Cache::has($cacheKey)) {
            return \Cache::get($cacheKey);
        }

        try {

           $archivingBox =  ArchivingBox::select('archiving_boxes.*')
                                    ->join('archiving_box_user', 
                                            'archiving_box_user.archiving_box_id', 
                                            '=', 'archiving_boxes.id')
                                    ->join('users',
                                        'users.id', '=', 
                                        'archiving_box_user.user_id')
                                        ->where('users.id', '=', $userId)
                                        ->orderBy('archiving_box_user.id', 'DESC')
                                        ->firstOrFail();

            \Cache::put($cacheKey, $archivingBox, 15);

            return $archivingBox;

        } catch (ModelNotFoundException $e) {
            return null;
        }


    }


    public static function currentUserArchivingBoxOrFail($userId) {

        $currentUserArchivingBox = self::currentUserArchivingBox($userId);

        if ($currentUserArchivingBox) {
            return $currentUserArchivingBox;
        }

        $message = "No archiving box specified";
        throw new NoUserArchivingBoxException($message);
    }


    // /**
    //  *Get most recent users
    //  *@param $limit : maximum amount of records to be returned
    //  *@return : Most recent users
    //  */
    // public static function mostRecentUsers($limit = 5) {

    //     $cacheKey = self::generateMostRecentUsersCacheKey($limit);

    //     if (\Cache::has($cacheKey)) {
    //         return \Cache::get($cacheKey);
    //     }

    //     $rawQuery = "(select name from access_levels 
    //                 where access_levels.id = users.access_level_id) as access_level";

    //     $users = User::select(\DB::raw($rawQuery), 'users.*' )
    //                             ->orderBy('id', 'DESC')
    //                             ->take($limit)->get();

    //     \Cache::put($cacheKey, $users, 15);

    //     return $users;
    // }



    public static function determineDayOfThisWeek() {
        $day = (int) date('w');

        if ($day == 0) {
            return 6;
        }

       return ($day - 1);
    }


    public static function weeklyUserPerformanceSummary($userId) {

         $today = date('Y-m-d');
         $week = date("W", strtotime($today)); // get week
         $y =    date("Y", strtotime($today)); // get year

         $dayOfThisWeek = self::determineDayOfThisWeek();

         $firstDate =  date('Y-m-d',strtotime($y."W".$week)); //first date 

         $weekDates = [];

         $weekDates[] = $firstDate;
         $days = [];

         $days [] = date("l", strtotime($firstDate));

         $totals = [];

         for ($i = 1; $i <= $dayOfThisWeek; $i++) {
            $lastDate = last($weekDates);
            $dayDate = date("Y-m-d",strtotime("+1 day", strtotime($lastDate)));
            $weekDates[] = $dayDate;
            $days [] = date("l", strtotime($dayDate));
         }

         $totals = self::weeklyUserTotalsBeforeToday($userId, $weekDates);

         array_push($totals, self::userTotalRecordsToday($userId));

         return [
            'dates' => $weekDates,
            'days' => $days,
            'totals' => $totals,
         ]; 

    }


    public static function weeklyUserTotalsBeforeToday($userId, $dates) {

        $cacheKey = "user-{$userId}-weekly-total-records-before-today";

        array_pop($dates);

        if (\Cache::has($cacheKey)) {
            return \Cache::get($cacheKey);
        }


        $totals = [];

        for ($i = 0; $i < count( $dates); $i++) {

            $dayDate = $dates[$i];
           
            $rawQuery  = "(SELECT count(*) FROM account_modifications ";
            $rawQuery .= " WHERE account_modifications.authorization_status = 1 ";
            $rawQuery .= " AND initiator_id = {$userId}  AND created_at > '{$dayDate} 00:00:00' ";
            $rawQuery .= " AND created_at < '{$dayDate} 23:59:59' ) as total";

            $totals[] = User::select(
            \DB::raw($rawQuery)
            )->first()->total;

        }

        \Cache::put($cacheKey, $totals, 180);

        return $totals;

    }



    public static function userTotalRecordsToday($userId) {

        $cacheKey = "user-{$userId}-total-records-today";

        $today = date('Y-m-d');

        if (\Cache::has($cacheKey)) {
            return \Cache::get($cacheKey);
        }

        $rawQuery  = "(SELECT count(*) FROM account_modifications ";
        $rawQuery .= " WHERE account_modifications.authorization_status = 1 ";
        $rawQuery .= " AND initiator_id = {$userId}  AND created_at > '{$today} 00:00:00' ";
        $rawQuery .= " AND created_at < '{$today} 23:59:59' ) as total";

        $total = User::select(
        \DB::raw($rawQuery)
        )->first()->total;

        \Cache::put($cacheKey, $total, 1);

        return $total;
    }

    public static function checkCredsAuthenticity($username,  $password) {

        $user = User::select()->where('username', '=', $username)
                              ->first();

        if (!$user || !\Hash::check($password, $user->password) ) {
            $message = "The credentials given are not authentic";
            throw new UserCredentialsNotAuthenticException($message);
        }

        return  $user;

    }


}
