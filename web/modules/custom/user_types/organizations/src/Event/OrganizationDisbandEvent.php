<?php

namespace Drupal\organizations\Event;

/**
 * Defines an organization disband event.
 *
 * When a manager decided not to manage an organization anymore.
 *
 * @see OrganizationManageResource::delete
 */
class OrganizationDisbandEvent extends OrganizationManageEvent {}
