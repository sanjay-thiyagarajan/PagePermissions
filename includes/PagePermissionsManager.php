<?php

use MediaWiki\Linker\LinkTarget;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserIdentity;

class PagePermissionsManager extends PermissionManager {

	/** @var LinkTarget|null */
	private $pagePermissionsPage;

	/** @var array|null */
	private $permittedRights;

	/**
	 * @param string $action
	 * @param User $user
	 * @param LinkTarget $page
	 * @param string $rigor
	 * @return bool
	 */
	public function userCan( $action, User $user, LinkTarget $page, $rigor = self::RIGOR_SECURE ): bool {
		$this->permittedRights = RequestContext::getMain()->getConfig()->get( 'PagePermissionsRoles' );
		$this->pagePermissionsPage = $page;
		
		$table = 'pagepermissions_rights';
		$vars[ 'user' ] = 'userid';
		$vars['type'] = 'permission';
		
		$dbr = wfGetDB( DB_REPLICA );
		
		$conds = [
			'page_id' => $page->getArticleId(),
			'userid' => $user->getId()
		];
		$res = $dbr->select( $table, $vars, $conds, __METHOD__ );
		
		if ( $res->current() ) {
			if ( in_array( $action, $this->permittedRights[ $res->current()->type ] ) ) {
				return true;
			} else {
				return false;
			}
		} else {
			return parent::userCan( $action, $user, $page, $rigor );
		}
		$this->pagePermissionsPage = null;
	}

	/**
	 * @param string $action
	 * @param User $user
	 * @param LinkTarget $page
	 * @param string $rigor
	 * @param array $ignoreErrors
	 * @return array[]
	 */
	public function getPermissionErrors(
		$action, User $user, LinkTarget $page, $rigor = self::RIGOR_SECURE, $ignoreErrors = []
	): array {
		$this->pagePermissionsPage = $page;
		$this->permittedRights = [];
		$return = parent::getPermissionErrors( $action, $user, $page, $rigor, $ignoreErrors );
		$this->pagePermissionsPage = null;
		if ( !$this->permittedRights ||  !in_array( $action, $this->permittedRights ) ) {
			foreach ( $return as &$error ) {
				if ( $error[0] === 'badaccess-groups' ) {
					$error = [ 'badaccess-group0' ];
				}
			}
		}
		$this->permittedRights = null;
		MWDebug::log( $action . ' ' . var_export( $return, true ) );
		wfDebug( __METHOD__ . ': ' . $action . ' ' . var_export( $return, true ) );
		return $return;
	}
}
