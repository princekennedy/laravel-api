<?php namespace App\Libraries;

use Request;


class LinksHelper {


	const URI_FMB_ADMIN_ACCOUNTS = '/admin/accounts';
	const URI_FMB_ADMIN_ACCOUNTS_REPORT_DOWNLOAD = '/admin/accounts-report/download';
	const URI_EDIT_OWN_ACCOUNT = '/my-account';

	const URI_CLERK_ENTRIES = '/clerks/entries';


	public static function  returnToAccountsFilter () {

		$link = Request::server('HTTP_REFERER');
		if ( $link ) {
			return $link;
		}

		return self::URI_FMB_ADMIN_ACCOUNTS;

	}

	public static function  exportAccountsReportUsingFilter () {
		$queryString = Request::getQueryString();
		$link = self::URI_FMB_ADMIN_ACCOUNTS_REPORT_DOWNLOAD .'?'. $queryString; 
		return $link;
	}


	public static function clerkPendingEntriesWithAuthorizationStatusToday($status) {
		$from = date('Y-m-d');
		$to = date('Y-m-d');
		return self::URI_CLERK_ENTRIES.'?'.'authorization='.
				$status.'&from_date='. $from .'&to_date='.$to;
	}


	public static function clerkPendingEntriesWithAuthorizationStatusThisMonth($status) {

        $from = date("Y-m-01");
        $lastMonthDay =  date("t");
        $to = date("Y-m-{$lastMonthDay}");

        return self::URI_CLERK_ENTRIES.'?'.'authorization='.
				$status.'&from_date='. $from .'&to_date='.$to;




	}



}





