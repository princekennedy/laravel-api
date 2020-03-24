<?php namespace App\Libraries;

use App\Entity;

class EntityHandler {

	const ENTITY_ID_USER = 1;
	const ENTITY_ID_USER_MODIFICATION = 2;

	/**
	*Get all entities
	*@return object with records.
	*/
	public static function getEntities() {
		$entities = Entity::all();
		return $entities;
	}



}


?>