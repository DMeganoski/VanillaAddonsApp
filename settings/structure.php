<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

// Use this file to construct tables and views necessary for your application.
// There are some examples below to get you started.

if (!isset($Drop))
   $Drop = FALSE;
   
if (!isset($Explicit))
   $Explicit = TRUE;
   
$SQL = $Database->SQL();
$Construct = $Database->Structure();

$Construct->Table('AddonType')
   ->PrimaryKey('AddonTypeID')
   ->Column('Label', 'varchar(50)')
   ->Column('Visible', array('1','0'), '1')
   ->Set($Explicit, $Drop);

if ($SQL->Select()->From('AddonType')->Get()->NumRows() == 0) {
   $SQL->Insert('AddonType', array('Label' => 'Plugin', 'Visible' => '1'));
   $SQL->Insert('AddonType', array('Label' => 'Theme', 'Visible' => '1'));
   $SQL->Insert('AddonType', array('Label' => 'Style', 'Visible' => '0'));
   $SQL->Insert('AddonType', array('Label' => 'Language', 'Visible' => '0'));
   $SQL->Insert('AddonType', array('Label' => 'Application', 'Visible' => '1'));
}

$Construct->Table('Addon')
   ->PrimaryKey('AddonID')
   ->Column('CurrentAddonVersionID', 'int', TRUE, 'key')
   ->Column('AddonTypeID', 'int', FALSE, 'key')
   ->Column('InsertUserID', 'int', FALSE, 'key')
   ->Column('UpdateUserID', 'int', TRUE)
   ->Column('Name', 'varchar(100)')
   ->Column('Icon', 'varchar(200)', TRUE)
   ->Column('Description', 'text', TRUE)
   ->Column('Requirements', 'text', TRUE)
   ->Column('CountComments', 'int', '0')
   ->Column('CountDownloads', 'int', '0')
   ->Column('Visible', array('1', '0'), '1')
   ->Column('Vanilla2', array('1', '0'), '1')
   ->Column('DateInserted', 'datetime')
   ->Column('DateUpdated', 'datetime', TRUE)
   ->Set($Explicit, $Drop);

$Construct->Table('AddonComment')
   ->PrimaryKey('AddonCommentID')
   ->Column('AddonID', 'int', FALSE, 'key')
   ->Column('InsertUserID', 'int', FALSE, 'key')
   ->Column('Body', 'text')
   ->Column('Format', 'varchar(20)', TRUE)
   ->Column('DateInserted', 'datetime')
   ->Set($Explicit, $Drop);

$Construct->Table('AddonVersion')
   ->PrimaryKey('AddonVersionID')
   ->Column('AddonID', 'int', FALSE, 'key')
   ->Column('File', 'varchar(200)', TRUE)
   ->Column('Version', 'varchar(20)')
   ->Column('TestedWith', 'text')
   ->Column('InsertUserID', 'int', FALSE, 'key')
   ->Column('DateInserted', 'datetime')
   ->Column('DateReviewed', 'datetime', TRUE)
   ->Set($Explicit, $Drop);

$Construct->Table('AddonPicture')
   ->PrimaryKey('AddonPictureID')
   ->Column('AddonID', 'int', FALSE, 'key')
   ->Column('File', 'varchar(200)')
   ->Column('DateInserted', 'datetime')
   ->Set($Explicit, $Drop);

$Construct->Table('Download')
   ->PrimaryKey('DownloadID')
   ->Column('AddonID', 'int', FALSE, 'key')
   ->Column('DateInserted', 'datetime')
   ->Column('RemoteIp', 'varchar(50)', TRUE)
   ->Set($Explicit, $Drop);

$Construct->Table('UpdateCheckSource')
   ->PrimaryKey('SourceID')
   ->Column('Location', 'varchar(255)', TRUE)
   ->Column('DateInserted', 'datetime', TRUE)
   ->Column('RemoteIp', 'varchar(50)', TRUE)
   ->Set($Explicit, $Drop);

$Construct->Table('UpdateCheck')
   ->PrimaryKey('UpdateCheckID')
   ->Column('SourceID', 'int', FALSE, 'key')
   ->Column('CountUsers', 'int', '0')
   ->Column('CountDiscussions', 'int', '0')
   ->Column('CountComments', 'int', '0')
   ->Column('CountConversations', 'int', '0')
   ->Column('CountConversationMessages', 'int', '0')
   ->Column('DateInserted', 'datetime')
   ->Column('RemoteIp', 'varchar(50)', TRUE)
   ->Set($Explicit, $Drop);

// Need to use this table instead of linking directly with the Addon table
// because we might not have all of the addons being checked for.
$Construct->Table('UpdateAddon')
   ->PrimaryKey('UpdateAddonID')
   ->Column('AddonID', 'int', FALSE, 'key')
   ->Column('Name', 'varchar(255)', TRUE)
   ->Column('Type', 'varchar(255)', TRUE)
   ->Column('Version', 'varchar(255)', TRUE)
   ->Set($Explicit, $Drop);
   
$Construct->Table('UpdateCheckAddon')
   ->Column('UpdateCheckID', 'int', FALSE, 'key')
   ->Column('UpdateAddonID', 'int', FALSE, 'key')
   ->Set($Explicit, $Drop);
   
$PermissionModel = Gdn::PermissionModel();
$PermissionModel->Database = $Database;
$PermissionModel->SQL = $SQL;

// Define some global addon permissions.
$PermissionModel->Define(array(
   'Addons.Addon.Add',
   'Addons.Addon.Manage',
   'Addons.Comments.Manage'
   ));

// Set the intial member permissions.
$PermissionModel->Save(array(
   'RoleID' => 8,
   'Addons.Addon.Add' => 1,
   'Addons.Addon.Manage' => 1,
   'Addons.Comments.Manage' => 1
   ));
   
// Set the initial administrator permissions.
$PermissionModel->Save(array(
   'RoleID' => 16,
   'Addons.Addon.Add' => 1,
   'Addons.Addon.Manage' => 1,
   'Addons.Comments.Manage' => 1
   ));

// Make sure that User.Permissions is blank so new permissions for users get applied.
$SQL->Update('User', array('Permissions' => ''))->Put();

// Insert some activity types
///  %1 = ActivityName
///  %2 = ActivityName Possessive
///  %3 = RegardingName
///  %4 = RegardingName Possessive
///  %5 = Link to RegardingName's Wall
///  %6 = his/her
///  %7 = he/she
///  %8 = RouteCode & Route

// X added an addon
if ($SQL->GetWhere('ActivityType', array('Name' => 'AddAddon'))->NumRows() == 0)
   $SQL->Insert('ActivityType', array('AllowComments' => '0', 'Name' => 'AddAddon', 'FullHeadline' => '%1$s uploaded a new %8$s.', 'ProfileHeadline' => '%1$s uploaded a new %8$s.', 'RouteCode' => 'addon', 'Public' => '1'));
   
// X edited an addon
if ($SQL->GetWhere('ActivityType', array('Name' => 'EditAddon'))->NumRows() == 0)
   $SQL->Insert('ActivityType', array('AllowComments' => '0', 'Name' => 'EditAddon', 'FullHeadline' => '%1$s edited an %8$s.', 'ProfileHeadline' => '%1$s edited an %8$s.', 'RouteCode' => 'addon', 'Public' => '1'));

// People's comments on addons
if ($SQL->GetWhere('ActivityType', array('Name' => 'AddonComment'))->NumRows() == 0)
   $SQL->Insert('ActivityType', array('AllowComments' => '0', 'Name' => 'AddonComment', 'FullHeadline' => '%1$s commented on %4$s %8$s.', 'ProfileHeadline' => '%1$s commented on %4$s %8$s.', 'RouteCode' => 'addon', 'Notify' => '1', 'Public' => '1'));

// People mentioning others in addon comments
if ($SQL->GetWhere('ActivityType', array('Name' => 'AddonCommentMention'))->NumRows() == 0)
   $SQL->Insert('ActivityType', array('AllowComments' => '0', 'Name' => 'AddonCommentMention', 'FullHeadline' => '%1$s mentioned %3$s in a %8$s.', 'ProfileHeadline' => '%1$s mentioned %3$s in a %8$s.', 'RouteCode' => 'comment', 'Notify' => '1', 'Public' => '0'));

// People adding new language definitions
if ($SQL->GetWhere('ActivityType', array('Name' => 'AddUserLanguage'))->NumRows() == 0)
   $SQL->Insert('ActivityType', array('AllowComments' => '0', 'Name' => 'AddUserLanguage', 'FullHeadline' => '%1$s added a new %8$s.', 'ProfileHeadline' => '%1$s added a new %8$s.', 'RouteCode' => 'language', 'Notify' => '0', 'Public' => '1'));

// People editing language definitions
if ($SQL->GetWhere('ActivityType', array('Name' => 'EditUserLanguage'))->NumRows() == 0)
   $SQL->Insert('ActivityType', array('AllowComments' => '0', 'Name' => 'EditUserLanguage', 'FullHeadline' => '%1$s edited a %8$s.', 'ProfileHeadline' => '%1$s edited a %8$s.', 'RouteCode' => 'language', 'Notify' => '0', 'Public' => '1'));

// Contains list of available languages for translating
$Construct->Table('Language')
   ->PrimaryKey('LanguageID')
   ->Column('Name', 'varchar(255)')
   ->Column('Code', 'varchar(10)')
   ->Column('InsertUserID', 'int', FALSE, 'key')
   ->Column('DateInserted', 'datetime')
   ->Column('UpdateUserID', 'int', TRUE)
   ->Column('DateUpdated', 'datetime', TRUE)
   ->Set($Explicit, $Drop);
   
// Contains relationships of who owns translations and who can edit translations (owner decides who can edit)
$Construct->Table('UserLanguage')
   ->PrimaryKey('UserLanguageID')
   ->Column('UserID', 'int', FALSE, 'key')
   ->Column('LanguageID', 'int', FALSE, 'key')
   ->Column('Owner', array('1', '0'), '0')
   ->Column('CountTranslations', 'int', '0') // The number of translations this UserLanguage contains
   ->Column('CountDownloads', 'int', '0')
   ->Column('CountLikes', 'int', '0')
   ->Set($Explicit, $Drop);

// Contains individual translations as well as source codes
$Construct->Table('Translation')
   ->PrimaryKey('TranslationID')
   ->Column('UserLanguageID', 'int', FALSE, 'key')
   ->Column('SourceTranslationID', 'int', TRUE, 'key') // This is the related TranslationID where LanguageID = 1 (the source codes for translations)
   ->Column('Application', 'varchar(100)', TRUE)
   ->Column('Value', 'text')
   ->Column('InsertUserID', 'int', FALSE, 'key')
   ->Column('DateInserted', 'datetime')
   ->Column('UpdateUserID', 'int', TRUE)
   ->Column('DateUpdated', 'datetime', TRUE)
   ->Set($Explicit, $Drop);

// Contains records of when actions were performed on userlanguages (ie. it is
// downloaded or "liked"). These values are aggregated in
// UserLanguage.CountLikes and UserLanguage.CountDownloads for faster querying,
// but saved here for reporting.
$Construct->Table('UserLanguageAction')
   ->PrimaryKey('UserLanguageActionID')
   ->Column('UserLanguageID', 'int', FALSE, 'key')
   ->Column('Action', 'varchar(20)') // The action being performed (ie. "download" or "like")
   ->Column('InsertUserID', 'int', TRUE, 'key') // Allows nulls because you do not need to be authenticated to download a userlanguage
   ->Column('DateInserted', 'datetime')
   ->Set($Explicit, $Drop);

// Make sure the default "source" translation exists
if ($SQL->GetWhere('Language', array('LanguageID' => 1))->NumRows() == 0)
   $SQL->Insert('Language', array('Name' => 'Source Codes', 'Code' => 'SOURCE', 'InsertUserID' => 1, 'DateInserted' => '2009-10-19 12:00:00'));

// Mark (UserID 1) owns the source translation
if ($SQL->GetWhere('UserLanguage', array('LanguageID' => 1, 'UserID' => 1))->NumRows() == 0)
   $SQL->Insert('UserLanguage', array('LanguageID' => 1, 'UserID' => 1, 'Owner' => '1'));
