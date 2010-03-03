<?php
/**
 * Creates a member based permission system for files
 *
 * @package securefiles
 * @author Hamish Campbell <hn.campbell@gmail.com>
 * @copyright copyright (c) 2010, Hamish Campbell 
 */
class SecureFileMemberPermissionDecorator extends DataObjectDecorator {
	
	function extraStatics() {
		return array(
			'many_many' => array(
				'MemberPermissions' => 'Member',
			),
		);
	}
	
	/**
	 * View permission check
	 * 
	 * @param Member $member
	 * @return noolean
	 */
	function canViewSecured(Member $member = null) {
		if($member) {
			return $this->owner->AllMemberPermissions()->containsIDs(array($member->ID));
		} else {
			return false;
		}
	}
	
	/**
	 * Collate permissions for this and all parent folders.
	 * 
	 * @return DataObjectSet
	 */
	function AllMemberPermissions() {
		$memberSet = new DataObjectSet();
		$members = $this->owner->MemberPermissions();
		foreach($members as $member)
			$memberSet->push($member);
		if($this->owner->ParentID)
			$memberSet->merge($this->owner->InheritedMemberPermissions());
		$memberSet->removeDuplicates();
		return $memberSet;
	}
	
	/**
	 * Collage permissions for all parent folders
	 * 
	 * @return DataObjectSet
	 */
	function InheritedMemberPermissions() {
		if($this->owner->ParentID)
			return $this->owner->Parent()->AllMemberPermissions();
		else
			return new DataObjectSet();
	}
	
	/**
	 * Adds group select fields to CMS
	 * 
 	 * @param FieldSet $fields
 	 * @return void
 	 */
	public function updateCMSFields(FieldSet &$fields) {
		
		// Only modify folder objects with parent nodes
		if(!($this->owner instanceof Folder) || !$this->owner->ID)
			return;
			
		// Only allow ADMIN and SECURE_FILE_SETTINGS members to edit these options
		if(!Permission::checkMember($member, array('ADMIN', 'SECURE_FILE_SETTINGS')))
			return;
		
		// Update Security Tab
		$secureFilesTab = $fields->findOrMakeTab('Root.'._t('SecureFiles.SECUREFILETABNAME', 'Security'));
		$secureFilesTab->push(new HeaderField(_t('SecureFiles.MEMBERACCESSTITLE', 'Member Access')));
		//_t('SecureFiles.MEMBERACCESSFIELD', 'Member Access Permissions')
		
		$secureFilesTab->push($memberTableField = new ManyManyComplexTableField(
				$this->owner,
				'MemberPermissions',
				'Member'
			)
		);
		$memberTableField->setPermissions(array());
			
		if($this->owner->InheritSecured()) {
			$permissionMembers = $this->owner->InheritedMemberPermissions();
			if($permissionMembers->Count()) {
				$fieldText = implode(", ", $permissionMembers->map());
			} else {
				$fieldText = _t('SecureFiles.NONE', "(None)");
			}
			$InheritedMembersField = new ReadonlyField("InheritedMemberPermissionsText", _t('SecureFiles.MEMBERINHERITEDPERMS', 'Inherited Member Permissions'), $fieldText);
			$InheritedGroupsField->addExtraClass('prependUnlock');
			$secureFilesTab->push($InheritedMembersField);
		}
	}
}