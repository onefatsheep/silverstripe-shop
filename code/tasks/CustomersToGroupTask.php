<?php
/**
 * Adds all customers to an assigned group.
 * 
 * @package shop
 * @subpackage tasks
 */
class CustomersToGroupTask extends BuildTask{
	
	protected $title = "Customers to Group";
	protected $description = "Adds all customers to an assigned group.";
	
	function run($request){
		$gp = DataObject::get_one("Group", "\"Title\" = '".ShopMember::get_group_name()."'");
		if(!$gp) {
			$gp = new Group();
			$gp->Title = ShopMember::get_group_name();
			$gp->Sort = 999998;
			$gp->write();
		}
		$allCombos = DB::query("Select \"ID\", \"MemberID\", \"GroupID\" FROM \"Group_Members\" WHERE \"Group_Members\".\"GroupID\" = ".$gp->ID.";");
		//make an array of all combos
		$alreadyAdded = array();
		$alreadyAdded[-1] = -1;
		if($allCombos) {
			foreach($allCombos as $combo) {
				$alreadyAdded[$combo["MemberID"]] = $combo["MemberID"];
			}
		}
		$unlistedMembers = DataObject::get(
			"Member",
			$where = "\"Member\".\"ID\" NOT IN (".implode(",",$alreadyAdded).")",
			$sort = null,
			$join = "INNER JOIN \"Order\" ON \"Order\".\"MemberID\" = \"Member\".\"ID\""
		);
		//add combos
		if($unlistedMembers) {
			$existingMembers = $gp->Members();
			foreach($unlistedMembers as $member) {
				$existingMembers->add($member);
			}
		}
	}
	
}