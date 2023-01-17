<?php 
/*!
 * Jetpack CRM
 * https://jetpackcrm.com
 * V3.0
 *
 * Copyright 2020 Automattic
 *
 * Date: 24/05/2019
 */

/* ======================================================
  Breaking Checks ( stops direct access )
   ====================================================== */
    if ( ! defined( 'ZEROBSCRM_PATH' ) ) exit;
/* ======================================================
  / Breaking Checks
   ====================================================== */

// temp/mvp solution. Some fields need different labels for export!
function zeroBSCRM_export_fieldReplacements() {

	return array(

		// Contacts/co
		'wpid'          => __('WordPress ID','zero-bs-crm'),
		'tw'            => __('Twitter Handle','zero-bs-crm'),
		'fb'            => __('Facebook Page','zero-bs-crm'),
		'li'            => __('LinkedIn','zero-bs-crm'),
		'avatar'        => __('Avatar','zero-bs-crm'),
		'created'       => __('Created Date','zero-bs-crm'),
		'lastupdated'   => __('Last Updated Date','zero-bs-crm'),
		'lastcontacted' => __('Last Contacted Date','zero-bs-crm'),

		// Quotes
		'id_override'    => __('Reference','zero-bs-crm'),
		'currency'       => __('Currency','zero-bs-crm'),
		'hash'           => __('Hash','zero-bs-crm'),
		'lastviewed'     => __('Last Viewed','zero-bs-crm'),
		'viewed_count'   => __('Viewed Count','zero-bs-crm'),
		'accepted'       => __('Accepted Date','zero-bs-crm'),
		'acceptedsigned' => __('Signed Date','zero-bs-crm'),
		'acceptedip'     => __('Signed via IP','zero-bs-crm'),

		// inv
		'status' => __('Status','zero-bs-crm'),

		// trans
		'origin'      => __('Origin','zero-bs-crm'),
		'customer_ip' => __('Customer IP','zero-bs-crm'),

	);
}

function zeroBSCRM_export_blockedFields() {
	
	return array(

		// global
		'zbs_site','zbs_team',

		// quotes
		'template','content','notes','send_attachments',

		// inv
		'pay_via','logo_url','pdf_template','portal_template','email_template','invoice_frequency','address_to_objtype','allow_partial','allow_tip','hours_or_quantity',

		// trans
		'parent', 'taxes', 'shipping_taxes'

	);

}


/* ======================================================
Export Tools
====================================================== */
function export_preflight_error( $message ) {
	echo '<h2>' . esc_html( $message ) . '</h2><button class="ui blue button" onclick="history.back()">Go Back</button>';
}

// based on what's posted, it'll show 'export routine'
function zeroBSCRM_page_exportRecords(){

	global $zbs;

	// bad perms
	if ( !zeroBSCRM_permsExport() ) {
		return export_preflight_error( __( 'You do not have permissions to access this page.' ) );
	}

	// == Retrieve objs to export =========

	// v3.0 is basic mvp, just exports based on ID's
	// 3.0+ should take list view params + regen

	$ids_to_export = array();

	// check for obj type passed?
	if ( !empty( $_GET['zbstype'] ) ) {

		$obj_type_str = sanitize_text_field( $_GET['zbstype'] );

	} else if ( !empty( $_POST['objtype'] ) ) {

		$obj_type_str = sanitize_text_field( $_POST['objtype'] );

	} else {

		// fallback as contact
		$obj_type_str = 'contact';

	}

	// get obj type ID
	$obj_type_id = $zbs->DAL->objTypeID( $obj_type_str );

	// bad object type, so fail
	if ( !$zbs->DAL->isValidObjTypeID($obj_type_id) ) {
		return export_preflight_error( __( 'Invalid object selected for export!' ) );
	}

	// no perms for this object
	if ( !zeroBSCRM_permsObjType( $obj_type_id ) ) {
		return export_preflight_error( __( 'You do not have permissions to access this page.' ) );
	}

	// got IDs
	if ( !empty( $_POST['ids'] ) ) {

		$potential_id_csv = sanitize_text_field( $_POST['ids'] );
		$potential_ids = explode( ',', $potential_id_csv );

		foreach ( $potential_ids as $potential_id ) {

			$id = (int)$potential_id;

			if ($id > 0 && !in_array( $id, $ids_to_export ) ) {
				$ids_to_export[] = $id;
			}

		}

		$obj_count = count( $ids_to_export );
		$ids_to_export_csv = implode( ',', $ids_to_export );

	} else {

		$obj_count = $zbs->DAL->getObjectLayerByType( $obj_type_id )->getFullCount();
		$ids_to_export_csv = 'all'; // :o HUGE?

	}

	// bad object type, so fail
	if ( $obj_count == 0 ) {
		return export_preflight_error( __( 'No objects to export!' ) );
	}

	// == / Retrieve objs to export =======

	// == Prep language + vars ============

	// what fields do we have to export?
	$fieldsAvailable = zeroBSCRM_export_produceAvailableFields($obj_type_id,true);

	// obj layer
	$objDALLayer = $zbs->DAL->getObjectLayerByType($obj_type_id);

	// language
	$objTypeSingular = $zbs->DAL->typeStr($obj_type_id,false);
	$objTypePlural = $zbs->DAL->typeStr($obj_type_id,true);

	// basic label 'contact/contacts'
	$exportTypeLabel = $objTypeSingular;
	if ($obj_count > 1) $exportTypeLabel = $objTypePlural;

	// == / Prep language + vars ===========

	// == UI ==============================

	// good to draw ?>
	<div id="zbs-export-wrap"><form method="post">

	<?php /* here we output the pre-requisites for the export (which is caught on init) */ ?>
	<input type="hidden" name="zbs-export-request" value="<?php echo esc_attr( time() ); ?>" />
	<?php wp_nonce_field( 'zbs_export_request', 'zbs-export-request-nonce' ); ?>
	<input type="hidden" name="zbs-export-request-objtype" value="<?php echo esc_attr( $obj_type_id ); ?>" />
	<input type="hidden" name="zbs-export-request-objids" value="<?php echo esc_attr( $ids_to_export_csv ); ?>" />

	<h2><?php echo esc_html__('Export','zero-bs-crm').' '. esc_html( zeroBSCRM_prettifyLongInts($obj_count).' '.$exportTypeLabel ); ?></h2>

	<div class="ui segment" id="zbs-export-filetype-wrap">
	<i class="file alternate outline icon"></i> <?php esc_html_e('Export as .CSV file','zero-bs-crm'); // later offer choice (excel variant? Outlook addressbook?) ?>
	</div>	

	<div class="ui segments" id="zbs-export-fields-wrap">
	<div class="ui segment header">
	<?php

	// header
	esc_html_e('Export Fields:','zero-bs-crm');

	// select all ?>
	<button type="button" class="ui blue mini button right floated all" id="zbs-export-select-all" ><span class="all"><i class="object ungroup icon"></i> <?php esc_html_e('Deselect All','zero-bs-crm'); ?></span><span class="none" style="display:none"><i class="object group icon"></i> <?php esc_html_e('Select All','zero-bs-crm'); ?></span></button>

	</div>
	<div class="ui segment" id="zbs-export-fields">
	<?php

	if (count($fieldsAvailable) > 0){

		$lastArea = ''; $openArea = false;

		// if got type, retrieve fields to (potentially) export.
		foreach ($fieldsAvailable as $fK => $field){

			/* Semantic checkboxes bugging, working on MVP so simplified for now.
			?>
			<div>
			<div class="ui checkbox">
			<input type="checkbox" id="zbs-export-field-<?php echo $fK; ?>"  name="zbs-export-field-<?php echo $fK; ?>">
			<label><?php echo $fLabel; ?></label>
			</div>
			</div>
			<?php */

			// area grouping
			if (isset($field['area']) && $field['area'] !== $lastArea){

				// close any open areas
				if ($openArea) {
					echo '</div>';
					$openArea = false;
				}

				if ($field['area'] !== ''){

					// open 'area'
					$openArea = true; $lastArea = $field['area'];
					?><div class="ui segment"><div class="ui small header"><?php echo esc_html( $field['area'] ); ?></div><?php

				}

			}

			?><input type="checkbox" id="zbs-export-field-<?php echo esc_attr( $fK ); ?>" name="zbs-export-field-<?php echo esc_attr( $fK ); ?>" checked="checked" value="<?php echo esc_attr( $fK ); ?>" /><label for="zbs-export-field-<?php echo esc_attr( $fK ); ?>"><?php echo esc_html( $field['label'] ); ?></label><br /><?php

		}

		// close any open areas
		if ($openArea) echo '</div>';

	} else {

		// nope.
		echo zeroBSCRM_UI2_messageHTML('warning',__('No fields to export','zero-bs-crm'),__('There were no fields found that we can export.','zero-bs-crm').'</a>','','zbs-legacy-export-tools-dialog');

	}

	?>
	</div>
	</div>

	<div class="ui divider"></div>

	<button class="ui green button" type="submit"><i class="download icon"></i> <?php esc_html_e('Export','zero-bs-crm'); ?></button>

	<script type="text/javascript">

	jQuery(function(){

		jQuery('#zbs-export-select-all').on( 'click', function(){

			if (jQuery(this).hasClass('none')){

				// check all
				jQuery('input:checkbox',jQuery('#zbs-export-fields')).prop( 'checked', true );

				jQuery(this).removeClass('none');
				jQuery('span.none').hide();
				jQuery('span.all').show();
				jQuery(this).addClass('all');

			} else {

				// deselect all
				jQuery('input:checkbox',jQuery('#zbs-export-fields')).prop( 'checked', false );

				jQuery(this).removeClass('all');
				jQuery('span.all').hide();
				jQuery('span.none').show();
				jQuery(this).addClass('none');

			}

		});

	});

	</script>

	</form></div><?php

	// == / UI ============================

}

/* ======================================================
  / Export Tools
   ====================================================== */


/* ======================================================
   Export Tools -> Actual Export File


		   				<input type="hidden" name="zbs-export-request" value="<?php echo time(); ?>" />
   						<?php wp_nonce_field( 'zbs_export_request', 'zbs-export-request-nonce' ); ?>
		   				<input type="hidden" name="zbs-export-request-objtype" value="<?php echo $obj_type_id; ?>" />
		   				<input type="hidden" name="zbs-export-request-objids" value="<?php echo $ids_to_export_csv; ?>" />
id="zbs-export-field-<?php echo $fK; ?>"
   ====================================================== */

	function zeroBSCRM_export_processExport(){

		global $zbs;

		// Check if valid posted export request
		// ++ nonce verifies
		// ++ is admin side
		// ++ is our page
		// ++ has perms
		if ( 
			isset($_POST) && isset($_POST['zbs-export-request']) 
			&& 
			isset( $_POST['zbs-export-request-nonce'] ) && wp_verify_nonce( $_POST['zbs-export-request-nonce'], 'zbs_export_request' ) 
			&& 
			is_admin()
			&& 
			isset($_GET['page']) && $_GET['page'] == $zbs->slugs['export-tools']
   			&& 
   			zeroBSCRM_permsExport()
			){

				$objTypeID = -1; $objIDArr = array(); $fields = array(); $extraParams = array('all'=>false);

				// == Param Retrieve ================================================

				// check obj type
	   			if (isset($_POST['zbs-export-request-objtype'])) {

	   				$potentialObjTypeID = (int)sanitize_text_field( $_POST['zbs-export-request-objtype'] );
	   				if ($zbs->DAL->isValidObjTypeID($potentialObjTypeID)) $objTypeID = $potentialObjTypeID;

	   			}

				// check id's
	   			if (isset($_POST['zbs-export-request-objids'])) {

	   				$potentialIDStr = sanitize_text_field( $_POST['zbs-export-request-objids'] );
	   				$potentialIDs = explode(',', $potentialIDStr);

	   				foreach ($potentialIDs as $potentialID){

	   					$i = (int)$potentialID;

	   					if ($i > 0 && !in_array($i, $objIDArr)) $objIDArr[] = $i;

	   				}

	   			}

	   			// catch extra params
	   			if (isset($potentialIDStr) && $potentialIDStr == 'all') $extraParams['all'] = true;


				// retrieve fields
				if (is_array($_POST)) foreach ($_POST as $k => $v){

					// retrieve all posted pre-keys
					if (substr($k,0,strlen('zbs-export-field-')) == 'zbs-export-field-'){

						// is *probably* one of ours (doesn't guarantee)
						$fieldKey = sanitize_text_field( $v );

						// some generic replacements:
						if ($fieldKey == 'ID') $fieldKey = 'id';
						if ($fieldKey == 'owner') $fieldKey = 'zbs_owner';

						// add
						if (!in_array($fieldKey, $fields)) $fields[] = $fieldKey;

					}

				}

	   			// == / Param Retrieve =============================================


	   			// == FINAL CHECKS? ================================================
	   			// Got acceptable objtype
	   			// Got fields to export
	   			// Got ID's to export (or all)
	   			if (
	   				$objTypeID > 0 && 
	   				is_array($fields) && count($fields) > 0
	   				&& 
	   				((is_array($objIDArr) && count($objIDArr) > 0) || $extraParams['all'])){

	   				// == obj type loading =========================================

			   			// obj layer
					   	$objDALLayer = $zbs->DAL->getObjectLayerByType($objTypeID);

		   				// what fields do we have to export?
			   			$fieldsAvailable = zeroBSCRM_export_produceAvailableFields($objTypeID);

			   			// language
			   			$objTypeSingular = $zbs->DAL->typeStr($objTypeID,false);
			   			$objTypePlural = $zbs->DAL->typeStr($objTypeID,true);

			   			// basic label 'contact/contacts'
			   			$exportTypeLabel = $objTypeSingular;
			   			if (count($objIDArr) > 1) $exportTypeLabel = $objTypePlural;

			   			// == obj type loading =========================================

						// general
						$filename = 'exported-'.$objTypePlural.'-'.date("d-m-Y_g-i-a").'.csv';

					// == file start ===================================================

						// send header
						header('Content-Type: text/csv; charset=utf-8');		
						header('Content-Disposition: attachment; filename= ' . $filename);

						// open output
						$output = fopen('php://output', 'w');

					// == / file start =================================================

		   			// == file gen =====================================================
				

						// column headers
						$columnHeaders = array(); foreach ($fields as $fK) {
							$label = $fK;
							if (isset($fieldsAvailable[$fK])) $label = $fieldsAvailable[$fK];
							$columnHeaders[] = $label;
							
							// for owners we add two columns, 1 = Owner ID, 2 = Owner username
							if ($fK == 'zbs_owner') $columnHeaders[] = __('Owner Username','zero-bs-crm');

						}
						fputcsv($output, $columnHeaders);

						// actual export lines

							// retrieve objs
							if ($extraParams['all']){
								$availObjs = $objDALLayer->getAll($objIDArr);
							} else {
								$availObjs = $objDALLayer->getIDList($objIDArr);
							}
							
							if (is_array($availObjs)) foreach ($availObjs as $obj){

								// per obj
								$objRow = array();
								foreach ($fields as $fK) {

									$v = ''; // default (means always right col count)
									if (isset($obj[$fK])) $v = zeroBSCRM_textExpose($obj[$fK]);

									// date objs use _date (which are formatted)
									if (isset($obj[$fK.'_date'])) $v = $obj[$fK.'_date'];
									// custom field dates too:
									if (isset($obj[$fK.'_cfdate'])) $v = $obj[$fK.'_cfdate'];

									// ownership - column 1 (ID)
									if ( $fK == 'zbs_owner' && !empty( $obj['owner']['ID'] ) ) {
										$v = $obj['owner']['ID'];
									}

									// catch legacy secaddr_addr1 issues. (only for contacts)
									// blurgh.
									// secaddr1 => secaddr_addr1
									if ($objTypeID == ZBS_TYPE_CONTACT && substr($fK,0,3) == 'sec'){
										if (isset($obj[str_replace('sec','secaddr_',$fK)])) $v = $obj[str_replace('sec','secaddr_',$fK)];
									}

									// here we account for linked objects
									// as of 4.1.1 this is contact/company for quote/invoice/transaction
									// passed in format: linked_obj_{OBJTYPEINT}_{FIELD}
									if ( substr( $fK, 0, 11) == 'linked_obj_' ){

										// take objtype from field
										$linked_obj_type_int_and_field = substr( $fK, 11);

										// split type and field
										$linked_obj_type_parts = explode( '_', $linked_obj_type_int_and_field, 2 );
										$linked_obj_type_int = (int)$linked_obj_type_parts[0]; // e.g. ZBS_TYPE_CONTACT = 1
										$linked_obj_field = $linked_obj_type_parts[1]; // e.g. 'ID'
										// retrieve sub object
										$linked_obj = jpcrm_export_retrieve_linked_object( $obj, $linked_obj_type_int );

										// retrieve field value
										if ( isset( $linked_obj[$linked_obj_field] ) ){

											// pass field as value, (provided is set)
											// e.g. id
											// note, other fields are also present here, so could expand to pass name etc.
											$v = $linked_obj[$linked_obj_field];

										}

									}

									// if -1 kill
									if ($v == -1) $v = '';

									$objRow[] = $v;

									// ownership - column 2 (Username)
									if ($fK == 'zbs_owner'){

										$v2 = '';

										/*
										With -1 owner it looks like this:

										'owner' => 
											array (
												'ID' => '-1',
												'OBJ' => false,
											),


										With 0 owner it looks like this:

										'owner' => false,

										With actual WP owner looks like this:

										'owner' => 
										array (
											'ID' => '234',
											'OBJ' => (object)WP_User,
										),
										*/

										// if has an owner
										if ( !empty( $obj['owner']['OBJ'] ) ) {

												$v2 = $obj['owner']['OBJ']->user_login;

										}

										$objRow[] = $v2;

									}

								} // / foreach field in each obj row

								// output row
								fputcsv($output, $objRow);

							} // / foreach obj

					// == / file gen ===================================================

					// == file fini ====================================================

						// send end
						fclose($output);
						exit();

					// == / file fini ==================================================

			} // / final checks

		} // / Check if valid posted export request

	}
	add_action('zerobscrm_post_init','zeroBSCRM_export_processExport');


/**
 * Takes an object being exported, and a linked object type, and returns the subobject
 * e.g. $obj could be a quote, linkedType could be contact, this would return the contact object against the quote
 *
 * @param array $obj (line being exported), int $linkedObjTypeInt CRM Object type
 *
 * @return array $sub object
 */
function jpcrm_export_retrieve_linked_object( $obj=array(), $linkedObjTypeInt=-1 ){

	global $zbs;

	// turn 1 into `contact`
	$linkedObjTypeKey = $zbs->DAL->objTypeKey($linkedObjTypeInt);

	// set contact ID
	// objects like quotes will have these as arrays under `contact` and `company`
	if ( is_array( $obj[$linkedObjTypeKey] ) && count( $obj[$linkedObjTypeKey] ) > 0 ){

		// noting here that object links can allow 1:many links, we only take the first
		if ( is_array( $obj[$linkedObjTypeKey][0] )){

			return $obj[$linkedObjTypeKey][0];

		}

	}

	return array();
}

// retrieves a formatted obj field array of what's actually 'allowed' to be exported
// Tested a few ways of achieving this, given the $globalfield limbo that v3 is in (legacy)
// - Using the $globalField method was unreliable, so rolled fresh using db-driven custom fields
// ... this could be rolled back into how we do $globalFields in road to v4 
// gh-253
function zeroBSCRM_export_produceAvailableFields($objTypeToExport=false,$includeAreas=false){

	global $zbs;

	// def
	$fieldsAvailable = array();

	// obj layer
   	$objDALLayer = $zbs->DAL->getObjectLayerByType($objTypeToExport);

   	// fields avail to export
   	// just base fields: $objLayerFields = $objDALLayer->objModel();
   	// base fields + custom fields:
   	$objLayerFields = $objDALLayer->objModelIncCustomFields(); 

   		// process
   		if (is_array($objLayerFields)){

   			$blockedFields = zeroBSCRM_export_blockedFields(); //,'zbs_owner'
   			$relabel = zeroBSCRM_export_fieldReplacements();

   			foreach ($objLayerFields as $fieldKey => $field){

   				// cf's need to alter this, so we var
   				$fieldKeyOutput = $fieldKey;

   				if (!in_array($fieldKey, $blockedFields)){

   					// simplify for output
   					if (!array_key_exists($fieldKey,$fieldsAvailable)){

   						/* e.g. : 
   						    [email] => Array
						        (
						            [fieldname] => zbsc_email
						            [format] => str
						            [input_type] => email
						            [label] => Email
						            [placeholder] => e.g. john@gmail.com
						            [essential] => 1
						        )

						    Note: 3.0.7+ also includes custom fields:

						    [test] => Array
						        (
						            [0] => text
						            [1] => test
						            [2] => test
						            [3] => test
						            [custom-field] => 1
						        )
						*/

						$label = $fieldKey; $area = '';

						if (!isset($field['custom-field'])){

							// Non CF stuff:

							// label
							if (isset($field['label'])) $label = $field['label'];

							// relabel?
							if (isset($relabel[$fieldKey])) $label = $relabel[$fieldKey];

							// addresses/areas (append)
							if (isset($field['area'])) {
								if (!$includeAreas) $label .= ' ('.$field['area'].')';
								$area = $field['area'];
							}

							// one exception:
							if ($label == 'zbs_owner') $label = __('Owner','zero-bs-crm');

						} else {

							// prefix $fieldKeyOutput
							$fieldKeyOutput = 'cf-'.$fieldKeyOutput;

							// Custom field passing
							if (isset($field[1])) $label = $field[1];

							// addresses/areas (append)
							if (isset($field['area'])) {
								if (!$includeAreas) $label .= ' ('.$field['area'].')';
								$area = $field['area'];
							}

							if ($area == '') $area = __('Custom Fields','zero-bs-crm');

						}

						if ($includeAreas)
							// with area
   							$fieldsAvailable[$fieldKey] = array('label'=>$label,'area'=>$area);
   						else
   							// simpler
   							$fieldsAvailable[$fieldKey] = $label;
   					}

   				}

   			}

   		}


   	// Add additional fields which are stored in the DB via obj links
   	// e.g. Invoice Contact
   	$linkedTypes = $objDALLayer->linkedToObjectTypes();
   	foreach ( $linkedTypes as $objectType ){

   		// for now, hard typed, we only add `ID`, `email`, and `name`, as these are a given for contacts + companies

   		// retrieve label (e.g. 'Contact')
   		$obj_label = $zbs->DAL->typeStr( $objectType );

   		// ID
	   		
	   		$label = $obj_label . ' ID';

			if ( $includeAreas ){
				// with area
				$fieldsAvailable['linked_obj_'.$objectType.'_id'] = array( 'label'=>$label ,'area'=> __( 'Linked Data', 'zero-bs-crm' ) );
			} else {
				// simpler
				$fieldsAvailable['linked_obj_'.$objectType.'_id'] = $label;
			}

   		// name
	   		
	   		$label = $obj_label . ' ' . __('Name','zero-bs-crm');

			if ( $includeAreas ){
				// with area
				$fieldsAvailable['linked_obj_'.$objectType.'_name'] = array( 'label'=>$label ,'area'=> __( 'Linked Data', 'zero-bs-crm' ) );
			} else {
				// simpler
				$fieldsAvailable['linked_obj_'.$objectType.'_name'] = $label;
			}

   		// email
	   		
	   		$label = $obj_label . ' ' . __('Email','zero-bs-crm');

			if ( $includeAreas ){
				// with area
				$fieldsAvailable['linked_obj_'.$objectType.'_email'] = array( 'label'=>$label ,'area'=> __( 'Linked Data', 'zero-bs-crm' ) );
			} else {
				// simpler
				$fieldsAvailable['linked_obj_'.$objectType.'_email'] = $label;
			}

   	}


   	return $fieldsAvailable;

}

/* ======================================================
   / Export Tools -> Actual Export File
   ====================================================== */
