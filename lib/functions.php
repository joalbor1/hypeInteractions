<?php

namespace hypeJunction\Interactions;

use ElggEntity;
use ElggObject;
use ElggRiverItem;

/**
 * Creates an object associated with a river item for commenting and other purposes
 * This is a workaround for river items that do not have an object or have an object that is group or user
 * 
 * @param ElggRiverItem $river River item
 * @return RiverObject|false
 */
function create_actionable_river_object(ElggRiverItem $river) {

	if (!$river instanceof ElggRiverItem) {
		return false;
	}

	$object = $river->getObjectEntity();
	if (!$object instanceof ElggObject) {
		$ia = elgg_set_ignore_access(true);

		$object = new RiverObject();
		$object->owner_guid = $river->subject_guid;
		$object->container_guid = $river->subject_guid;
		$object->access_id = $river->access_id;
		$object->river_id = $river->id;
		$object->save();

		elgg_set_ignore_access($ia);
	}

	return $object;
}

/**
 * Get an actionable object associated with the river item
 * This could be a river object entity or a special entity that was created for this river item
 *
 * @param ElggRiverItem $river River item
 * @return ElggObject|false
 */
function get_river_object(ElggRiverItem $river) {

	if (!$river instanceof ElggRiverItem) {
		return false;
	}

	$object = $river->getObjectEntity();
	if ($object instanceof ElggObject) {
		return $object;
	}

	// wrapping this in ignore access so that we do not accidentally create duplicate
	// river objects
	$ia = elgg_set_ignore_access(true);
	$objects = elgg_get_entities_from_metadata(array(
		'types' => RiverObject::TYPE,
		'subtypes' => array(RiverObject::SUBTYPE, 'hjstream'),
		'metadata_name_value_pairs' => array(
			'name' => 'river_id',
			'value' => $river->id,
			'operand' => '='
		),
		'limit' => 1,
	));
	$guid = ($objects) ? $objects[0]->guid : false;
	elgg_set_ignore_access($ia);

	if (!$guid) {
		$object = create_actionable_river_object($river);
		$guid = $object->guid;
	}

	return get_entity($guid);
}

/**
 * Get interaction statistics
 *
 * @param ElggEntity $entity Entity
 * @return array
 */
function get_stats($entity) {

	if (!$entity instanceof ElggEntity) {
		return array();
	}

	$stats = array(
		'comments' => array(
			'count' => $entity->countComments()
		),
		'likes' => array(
			'count' => $entity->countAnnotations('likes'),
			'state' => (elgg_annotation_exists($entity->guid, 'likes')) ? 'after' : 'before',
		)
	);

	return elgg_trigger_plugin_hook('get_stats', 'interactions', array('entity' => $entity), $stats);
}

/**
 * Get entity URL wrapped in an <a></a> tag
 * @return string
 */
function get_linked_entity_name($entity) {
	if (elgg_instanceof($entity)) {
		return elgg_view('output/url', array(
			'text' => $entity->getDisplayName(),
			'href' => $entity->getURL(),
			'is_trusted' => true,
		));
	}
	return '';
}
