{*<!--
/*********************************************************************************
** The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ********************************************************************************/
-->*}

<script>
if (typeof(e) != 'undefined') {
	window.captureEvents(Event.MOUSEMOVE);
}
// window.onmousemove= displayCoords;
// window.onclick = fnRevert;
function displayCoords(currObj,obj,mode,curr_row) {ldelim}
	if(mode != 'discount_final' && mode != 'sh_tax_div_title' && mode != 'group_tax_div_title')
	{ldelim}
		var curr_productid = document.getElementById('hdnProductId'+curr_row).value;
		if(curr_productid == '')
		{ldelim}
			alert('{$APP.PLEASE_SELECT_LINE_ITEM}');
			return false;
		{rdelim}
		var curr_quantity = document.getElementById('qty'+curr_row).value;
		if(curr_quantity == '')
		{ldelim}
			alert('{$APP.PLEASE_FILL_QUANTITY}');
			return false;
		{rdelim}
	{rdelim}

	//Set the Header value for Discount
	if(mode == 'discount')
	{ldelim}
		document.getElementById('discount_div_title'+curr_row).innerHTML = '<b>{$APP.LABEL_SET_DISCOUNT_FOR_COLON} '+document.getElementById('productTotal'+curr_row).innerHTML+'</b>';
	{rdelim}
	else if(mode == 'tax')
	{ldelim}
		document.getElementById('tax_div_title'+curr_row).innerHTML = '<b>{$APP.LABEL_SET_TAX_FOR} '+document.getElementById('totalAfterDiscount'+curr_row).innerHTML+'</b>';
	{rdelim}
	else if(mode == 'discount_final')
	{ldelim}
		document.getElementById('discount_div_title_final').innerHTML = '<b>{$APP.LABEL_SET_DISCOUNT_FOR} '+document.getElementById('netTotal').innerHTML+'</b>';
	{rdelim}
	else if(mode == 'sh_tax_div_title')
	{ldelim}
		document.getElementById('sh_tax_div_title').innerHTML = '<b>{$APP.LABEL_SET_SH_TAX_FOR_COLON} '+document.getElementById('shipping_handling_charge').value+'</b>';
	{rdelim}
	else if(mode == 'group_tax_div_title')
	{ldelim}
		var net_total_after_discount = eval(document.getElementById('netTotal').innerHTML)-eval(document.getElementById('discountTotal_final').innerHTML);
		document.getElementById('group_tax_div_title').innerHTML = '<b>{$APP.LABEL_SET_GROUP_TAX_FOR_COLON} '+net_total_after_discount+'</b>';
	{rdelim}

	fnvshobj(currObj,'tax_container');
	if(document.all)
	{ldelim}
		var divleft = document.getElementById('tax_container').style.left;
		var divabsleft = divleft.substring(0,divleft.length-2);
		document.getElementById(obj).style.left = eval(divabsleft) - 120;

		var divtop = document.getElementById('tax_container').style.top;
		var divabstop = divtop.substring(0,divtop.length-2);
		document.getElementById(obj).style.top = eval(divabstop);
	{rdelim}else
	{ldelim}
		document.getElementById(obj).style.left = document.getElementById('tax_container').left;
		document.getElementById(obj).style.top = document.getElementById('tax_container').top;
	{rdelim}
	document.getElementById(obj).style.display = 'block';
{rdelim}
{if empty($moreinfofields)}
	var moreInfoFields = Array();
{else}
	var moreInfoFields = Array({$moreinfofields});
{/if}
</script>

<tr><td colspan="4" align="left">

<table width="100%" border="0" align="center" cellpadding="5" cellspacing="0" class="crmTable" id="proTab">
	<tr>
	<td colspan="3" class="dvInnerHeader">
		<b>{$APP.LBL_ITEM_DETAILS}</b>
	</td>
	<td class="dvInnerHeader" align="center" colspan="2">
		<input type="hidden" value="{$INV_CURRENCY_ID}" id="prev_selected_currency_id" />
		<b>{$APP.LBL_CURRENCY}</b>&nbsp;&nbsp;
		<select class="small" id="inventory_currency" name="inventory_currency" onchange="updatePrices();">
		{foreach item=currency_details key=count from=$CURRENCIES_LIST}
			{if $currency_details.curid eq $INV_CURRENCY_ID}
				{assign var=currency_selected value="selected"}
			{else}
				{assign var=currency_selected value=""}
			{/if}
			<OPTION value="{$currency_details.curid}" {$currency_selected}>{$currency_details.currencylabel|@getTranslatedCurrencyString} ({$currency_details.currencysymbol})</OPTION>
		{/foreach}
		</select>
	</td>
	<td class="dvInnerHeader" align="center" colspan="2">
		<b>{$APP.LBL_TAX_MODE}</b>&nbsp;&nbsp;
		{assign var="group_selected" value=""}
		{assign var="individual_selected" value=""}
		{if $ASSOCIATEDPRODUCTS.1.final_details.taxtype eq 'group'}
			{assign var="group_selected" value="selected"}
		{else}
			{assign var="individual_selected" value="selected"}
		{/if}
		<select class="small" id="taxtype" name="taxtype" onchange="decideTaxDiv(); calcTotal();">
			<OPTION value="individual" {$individual_selected}>{$APP.LBL_INDIVIDUAL}</OPTION>
			<OPTION value="group" {$group_selected}>{$APP.LBL_GROUP}</OPTION>
		</select>
	</td>
	</tr>

   <!-- Header for the Product Details -->
   <tr valign="top">
	<td width=5% valign="top" class="lvtCol" align="right"><b>{$APP.LBL_TOOLS}</b></td>
	<td width=35% class="lvtCol"><font color='red'>*</font><b>{$APP.LBL_ITEM_NAME}</b></td>
	<td width=20% class="lvtCol"><b>{$APP.LBL_INFORMATION}</b></td>
	<td width=10% class="lvtCol"><b>{$APP.LBL_QTY}</b></td>
	<td width=10% class="lvtCol" align="right"><b>{if $MODULE == 'PurchaseOrder'}{$APP.LBL_PURCHASE_PRICE}{else}{$APP.LBL_LIST_PRICE}{/if}</b></td>
	<td width=10% nowrap class="lvtCol" align="right"><b>{$APP.LBL_TOTAL}</b></td>
	<td width=10% valign="top" class="lvtCol" align="right"><b>{$APP.LBL_NET_PRICE}</b></td>
   </tr>

   {foreach key=row_no item=data from=$ASSOCIATEDPRODUCTS name=outer1}
	{assign var="deleted" value="deleted"|cat:$row_no}
	{assign var="hdnProductId" value="hdnProductId"|cat:$row_no}
	{assign var="productName" value="productName"|cat:$row_no}
	{assign var="comment" value="comment"|cat:$row_no}
	{assign var="productDescription" value="productDescription"|cat:$row_no}
	{assign var="qtyInStock" value="qtyInStock"|cat:$row_no}
	{assign var="qty" value="qty"|cat:$row_no}
	{assign var="listPrice" value="listPrice"|cat:$row_no}
	{assign var="productTotal" value="productTotal"|cat:$row_no}
	{assign var="subproduct_ids" value="subproduct_ids"|cat:$row_no}
	{assign var="subprod_names" value="subprod_names"|cat:$row_no}
	{assign var="entityIdentifier" value="entityType"|cat:$row_no}
	{assign var="entityType" value=$data.$entityIdentifier}
	{assign var="lineitem_id" value="lineitem_id"|cat:$row_no}
	{assign var="rel_lineitem_id" value="rel_lineitem_id"|cat:$row_no}
	{assign var="moreinfo" value="moreinfo"|cat:$row_no}

	{assign var="discount_type" value="discount_type"|cat:$row_no}
	{assign var="discount_percent" value="discount_percent"|cat:$row_no}
	{assign var="checked_discount_percent" value="checked_discount_percent"|cat:$row_no}
	{assign var="style_discount_percent" value="style_discount_percent"|cat:$row_no}
	{assign var="discount_amount" value="discount_amount"|cat:$row_no}
	{assign var="checked_discount_amount" value="checked_discount_amount"|cat:$row_no}
	{assign var="style_discount_amount" value="style_discount_amount"|cat:$row_no}
	{assign var="checked_discount_zero" value="checked_discount_zero"|cat:$row_no}

	{assign var="discountTotal" value="discountTotal"|cat:$row_no}
	{assign var="totalAfterDiscount" value="totalAfterDiscount"|cat:$row_no}
	{assign var="taxTotal" value="taxTotal"|cat:$row_no}
	{assign var="netPrice" value="netPrice"|cat:$row_no}

   <tr id="row{$row_no}" valign="top" data-corebosinvrow=1>

	<!-- column 1 - delete link - starts -->
	<td class="crmTableRow small lineOnTop inv-editview__toolscol">
		{if $row_no neq 1}
			<img src="{'delete.gif'|@vtiger_imageurl:$THEME}" border="0" onclick="deleteRow('{$MODULE}',{$row_no})" style="cursor:pointer;" title="{'LBL_DELETE'|@getTranslatedString:'Settings'}">
		{/if}<br/><br/>
		{if $row_no neq 1}
			&nbsp;<a href="javascript:moveUpDown('UP','{$MODULE}',{$row_no})" title="{'LBL_MOVE'|@getTranslatedString:'Settings'} {'LBL_UP'|@getTranslatedString:'Settings'}"><img src="{'up_layout.gif'|@vtiger_imageurl:$THEME}" border="0"></a>
		{/if}
		{if not $smarty.foreach.outer1.last}
			&nbsp;<a href="javascript:moveUpDown('DOWN','{$MODULE}',{$row_no})" title="{'LBL_MOVE'|@getTranslatedString:'Settings'} {'LBL_DOWN'|@getTranslatedString:'Settings'}"><img src="{'down_layout.gif'|@vtiger_imageurl:$THEME}" border="0" ></a>
		{/if}
		<input type="hidden" id="{$deleted}" name="{$deleted}" value="0">
		<input type="hidden" id="{$lineitem_id}" name="{$lineitem_id}" value="{$data[$lineitem_id]}">
		<input type="hidden" id="{$rel_lineitem_id}" name="{$rel_lineitem_id}" value="{$data[$rel_lineitem_id]}">
	</td>

	<!-- column 2 - Product Name - starts -->
	<td class="crmTableRow small lineOnTop inv-editview__namecol">
		<!-- Product Re-Ordering Feature Code Addition Starts -->
		<input type="hidden" name="hidtax_row_no{$row_no}" id="hidtax_row_no{$row_no}" value="{if isset($tax_row_no)}{$tax_row_no}{/if}"/>
		<!-- Product Re-Ordering Feature Code Addition ends -->
		<table width="100%"  border="0" cellspacing="0" cellpadding="1">
			<tr>
				<td class="small cblds-p_none" valign="top">
					<div class="slds-combobox_container slds-has-inline-listbox cbds-product-search" style="width:70%;display:inline-block">
						<div class="slds-combobox slds-dropdown-trigger slds-dropdown-trigger_click slds-combobox-lookup" aria-expanded="false" aria-haspopup="listbox" role="combobox">
							<div class="slds-combobox__form-element slds-input-has-icon slds-input-has-icon_right" role="none">
								<input id="{$productName}" name="{$productName}" class="slds-input slds-combobox__input cbds-inventoryline__input_name" aria-autocomplete="list" aria-controls="listbox-unique-id" autocomplete="off" role="textbox" placeholder="{$APP.typetosearch_prodser}" value="{$data.$productName}" type="text" style="box-shadow: none;">
								<span class="slds-icon_container slds-icon-utility-search slds-input__icon slds-input__icon_right">
									<svg class="slds-icon slds-icon slds-icon_x-small slds-icon-text-default" aria-hidden="true">
										<use xlink:href="include/LD/assets/icons/utility-sprite/svg/symbols.svg#search"></use>
									</svg>
								</span>
								<div class="slds-input__icon-group slds-input__icon-group_right">
									<div role="status" class="slds-spinner slds-spinner_brand slds-spinner_x-small slds-input__spinner slds-hide">
										<span class="slds-assistive-text">{'LBL_LOADING'|@getTranslatedString}</span>
										<div class="slds-spinner__dot-a"></div>
										<div class="slds-spinner__dot-b"></div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<input type="hidden" id="{$hdnProductId}" name="{$hdnProductId}" value="{$data.$hdnProductId}" />
					<input type="hidden" id="lineItemType{$row_no}" name="lineItemType{$row_no}" value="{$entityType}" />
					&nbsp;
					{if $entityType eq 'Services'}
						<img id="searchIcon{$row_no}" title="{'Services'|@getTranslatedString:'Services'}" src="{'services.gif'|@vtiger_imageurl:$THEME}" style="cursor: pointer;" align="absmiddle" onclick="servicePickList(this,'{$MODULE}','{$row_no}')" />
					{else}
						<img id="searchIcon{$row_no}" title="{'Products'|@getTranslatedString:'Products'}" src="{'products.gif'|@vtiger_imageurl:$THEME}" style="cursor: pointer;" align="absmiddle" onclick="productPickList(this,'{$MODULE}','{$row_no}')" />
					{/if}
				</td>
			</tr>
			<tr>
				<td class="small cblds-p_xx-small">
					<input type="hidden" value="{$data.$subproduct_ids}" id="{$subproduct_ids}" name="{$subproduct_ids}" />
					<span id="{$subprod_names}" name="{$subprod_names}" style="color:#C0C0C0;font-style:italic;">{$data.$subprod_names}</span>
				</td>
			</tr>
			<tr>
				<td class="small cblds-p_none" id="setComment">
					<textarea id="{$comment}" name="{$comment}" class=small style="{$Inventory_Comment_Style}">{$data.$comment}</textarea>
					<img src="{'clear_field.gif'|@vtiger_imageurl:$THEME}" onClick="getObj('{$comment}').value=''" style="cursor:pointer;" />
				</td>
			</tr>
		</table>
	</td>
	<!-- column 2 - Product Name - ends -->

	<!-- column 3 - Quantity in Stock - starts -->
	<td class="crmTableRow small lineOnTop inv-editview__infocol" valign="top">
		{if (in_array($MODULE, getInventoryModules()) && $MODULE != 'PurchaseOrder') && 'Products'|vtlib_isModuleActive}
		{$APP.LBL_QTY_IN_STOCK}:&nbsp;<span id="{$qtyInStock}">{$data.$qtyInStock}</span><br>
		{/if}
		{if isset($data.$moreinfo)}
		{foreach item=maindata from=$data.$moreinfo}
			{include file='Inventory/EditViewUI.tpl'}
		{/foreach}
		{/if}
	</td>
	<!-- column 3 - Quantity in Stock - ends -->

	<!-- column 4 - Quantity - starts -->
	<td class="crmTableRow small lineOnTop inv-editview__qtycol" valign="top">
		{if $TAX_TYPE eq 'group'}
			<input id="{$qty}" name="{$qty}" type="text" class="small " style="width:50px" onBlur="settotalnoofrows(); calcTotal(); loadTaxes_Ajax('{$row_no}'); calcGroupTax();{if $MODULE eq 'Invoice' && $entityType neq 'Services'} stock_alert('{$row_no}');{/if}" onChange="setDiscount(this,'{$row_no}')" value="{$data.$qty}"/><br><span id="stock_alert{$row_no}"></span>
		{else}
			<input id="{$qty}" name="{$qty}" type="text" class="small " style="width:50px" onBlur="settotalnoofrows(); calcTotal(); loadTaxes_Ajax('{$row_no}'); {if $MODULE eq 'Invoice' && $entityType neq 'Services'} stock_alert('{$row_no}');{/if}" onChange="setDiscount(this,'{$row_no}')" value="{$data.$qty}"/><br><span id="stock_alert{$row_no}"></span>
		{/if}
	</td>
	<!-- column 4 - Quantity - ends -->

	<!-- column 5 - List Price with Discount, Total After Discount and Tax as table - starts -->
	<td class="crmTableRow small lineOnTop inv-editview__pricecol" valign="top">
		<table class="slds-table slds-table_cell-buffer">
		<tbody>
		   <tr>
			<td style="padding-right:0px;">
				<input id="{$listPrice}" name="{$listPrice}" value="{$data.$listPrice}" type="text" class="small" style="width:70px" onBlur="calcTotal(); setDiscount(this,'{$row_no}');callTaxCalc('{$row_no}');"{if $Inventory_ListPrice_ReadOnly} readonly{/if}/>&nbsp;{if 'PriceBooks'|vtlib_isModuleActive}<img src="{'pricebook.gif'|@vtiger_imageurl:$THEME}" onclick="priceBookPickList(this,'{$row_no}')">{/if}
			</td>
		   </tr>
		   <tr>
			<td style="padding:5px;" nowrap>
				(-)&nbsp;<b><a href="javascript:doNothing();" onClick="displayCoords(this,'discount_div{$row_no}','discount','{$row_no}')" >{$APP.LBL_DISCOUNT}</a> : </b>
				<div class="discountUI" id="discount_div{$row_no}">
					<input type="hidden" id="discount_type{$row_no}" name="discount_type{$row_no}" value="{if isset($data.$discount_type)}{$data.$discount_type}{/if}">
					<table class="slds-table slds-table_cell-buffer slds-table_bordered">
					<thead>
						<tr class="slds-line-height_reset">
						<th id="discount_div_title{$row_no}" class="slds-p-left_none" scope="col"></th>
						<th class="cblds-t-align_right slds-p-right_none" scope="col"><img src="{'close.gif'|@vtiger_imageurl:$THEME}" border="0" onClick="fnhide('discount_div{$row_no}')" style="cursor:pointer;"></th>
						</tr>
					</thead>
					<tbody>
					   <tr>
						<td class="lineOnTop" style="padding-left: 4px; text-align: left !important;">
							<input type="radio" name="discount{$row_no}" {if isset($data.$checked_discount_zero)}{$data.$checked_discount_zero}{/if} onclick="setDiscount(this,'{$row_no}'); callTaxCalc('{$row_no}');calcTotal();">&nbsp; {$APP.LBL_ZERO_DISCOUNT}
						</td>
						<td class="lineOnTop ">&nbsp;</td>
					   </tr>
					   <tr>
						<td style="padding-left: 4px; text-align: left !important;">
							<input type="radio" name="discount{$row_no}" onclick="setDiscount(this,'{$row_no}'); callTaxCalc('{$row_no}'); calcTotal();" {if isset($data.$checked_discount_percent)}{$data.$checked_discount_percent}{/if}>&nbsp; % {$APP.LBL_OF_PRICE}
						</td>
						<td style="padding-left: 2px; padding-right: 4px;">
							<input type="text" class="small" size="5" id="discount_percentage{$row_no}" name="discount_percentage{$row_no}" value="{$data.$discount_percent}" {if isset($data.$style_discount_percent)}{$data.$style_discount_percent}{/if} onBlur="setDiscount(this,'{$row_no}'); callTaxCalc('{$row_no}'); calcTotal();">&nbsp;%
						</td>
					   </tr>
					   <tr>
						<td nowrap style="padding-left: 4px; text-align: left !important;">
							<input type="radio" name="discount{$row_no}" onclick="setDiscount(this,'{$row_no}'); callTaxCalc('{$row_no}'); calcTotal();" {if isset($data.$checked_discount_amount)}{$data.$checked_discount_amount}{/if}>&nbsp;{$APP.LBL_DIRECT_PRICE_REDUCTION}
						</td>
						<td style="padding-left: 2px; padding-right: 4px;">
							<input type="text" id="discount_amount{$row_no}" name="discount_amount{$row_no}" size="5" value="{$data.$discount_amount}" {if isset($data.$style_discount_amount)}{$data.$style_discount_amount}{/if} onBlur="setDiscount(this,{$row_no}); callTaxCalc('{$row_no}'); calcTotal();">
						</td>
					   </tr>
					</tbody>
					</table>
				</div>
			</td>
		   </tr>
		   <tr>
			<td align="right" style="padding:5px;" nowrap>
				<b>{$APP.LBL_TOTAL_AFTER_DISCOUNT} :</b>
			</td>
		   </tr>
		   <tr id="individual_tax_row{$row_no}" class="TaxShow">
			<th class="cblds-t-align_right" style="padding:5px;" nowrap>
				(+)&nbsp;<b><a href="javascript:doNothing();" onClick="displayCoords(this,'tax_div{$row_no}','tax','{$row_no}')" >{$APP.LBL_TAX} </a> : </b>
				<div class="discountUI" id="tax_div{$row_no}">
					<!-- we will form the table with all taxes -->
					<table class="slds-table slds-table_cell-buffer slds-table_bordered" id="tax_table{$row_no}">
					<thead>
						<tr class="slds-line-height_reset">
						<th id="tax_div_title{$row_no}" class="slds-p-left_none" scope="col" colspan="2">&nbsp;{$APP.LABEL_SET_TAX_FOR} : {$data.$totalAfterDiscount}</th>
						<th class="cblds-t-align_right slds-p-right_none" scope="col"><img src="{'close.gif'|@vtiger_imageurl:$THEME}" border="0" onClick="fnhide('tax_div{$row_no}')" style="cursor:pointer;"></th>
						</tr>
					</thead>
					{if isset($data.taxes)}
					<tbody>
					{foreach key=tax_row_no item=tax_data from=$data.taxes name=taxloop}
						{assign var="taxname" value=$tax_data.taxname|cat:"_percentage"|cat:$row_no}
						{assign var="taxlinerowno" value=$tax_row_no+1}
						{assign var="tax_id_name" value="hidden_tax"|cat:$taxlinerowno|cat:"_percentage"|cat:$row_no}
						{assign var="taxlabel" value=$tax_data.taxlabel|cat:"_percentage"|cat:$row_no}
						{assign var="popup_tax_rowname" value="popup_tax_row"|cat:$row_no}
						<tr class="slds-hint-parent">
						<td scope="row" class="slds-p-left_none">
							<input type="text" class="small" size="5" name="{$taxname}" id="{$taxname}" value="{$tax_data.percentage}" onBlur="calcCurrentTax('{$taxname}',{$row_no},{$tax_row_no})">&nbsp;%
							<input type="hidden" id="{$tax_id_name}" value="{$taxname}">
						</td>
						<td style="padding-left: 2px;padding-right: 2px;">{$tax_data.taxlabel}&nbsp;</td>
						<td class="cblds-t-align_right slds-p-right_none">
							<input type="text" class="small" size="6" name="{$popup_tax_rowname}" id="{$popup_tax_rowname}{$smarty.foreach.taxloop.iteration}" style="cursor:pointer;" value="0.0" readonly>
						</td>
						</tr>
					{/foreach}
					</tbody>
					{/if}
					</table>
				</div>
				<!-- This above div is added to display the tax informations -->
			</td>
		   </tr>
		</tbody>
		</table>
	</td>
	<!-- column 5 - List Price with Discount, Total After Discount and Tax as table - ends -->

	<!-- column 6 - Product Total - starts -->
	<td class="crmTableRow small lineOnTop inv-editview__totalscol">
		<table class="slds-table slds-table_cell-buffer">
		<tbody>
		   <tr>
			<td id="productTotal{$row_no}" style="padding-top:6px;">{$data.$productTotal}</td>
		   </tr>
		   <tr>
			<td id="discountTotal{$row_no}" style="padding-top:6px;">{$data.$discountTotal}</td>
		   </tr>
		   <tr>
			<td id="totalAfterDiscount{$row_no}" style="padding-top:6px;">{$data.$totalAfterDiscount}</td>
		   </tr>
		   <tr>
			<td id="taxTotal{$row_no}" style="padding-top:6px;">{$data.$taxTotal}</td>
		   </tr>
		</tbody>
		</table>
	</td>
	<!-- column 6 - Product Total - ends -->

	<!-- column 7 - Net Price - starts -->
	<td valign="bottom" class="crmTableRow small lineOnTop inv-editview__netpricecol">
		<span id="netPrice{$row_no}"><b>{$data.$netPrice}</b></span>
	</td>
	<!-- column 7 - Net Price - ends -->

   </tr>
   <!-- Product Details First row - Ends -->
	{if !empty($customtemplaterows)}
		{include file=$customtemplaterows ROWNO=$row_no ITEM=$data}
	{/if}
   {/foreach}
</table>

<table width="100%"  border="0" align="center" cellpadding="5" cellspacing="0" class="crmTable">
   <!-- Add Product Button -->
   <tr>
	<td colspan="3" class="cblds-p_medium">
		{if 'Products'|vtlib_isModuleActive}
		<input type="button" name="Button" class="crmbutton small create" value="{$APP.LBL_ADD_PRODUCT}" onclick="fnAddProductRow('{$MODULE}');" />
		{/if}
		{if 'Services'|vtlib_isModuleActive}
		&nbsp;&nbsp;
		<input type="button" name="Button" class="crmbutton small create" value="{$APP.LBL_ADD_SERVICE}" onclick="fnAddServiceRow('{$MODULE}');" />
		{/if}
	</td>
   </tr>

<!--
All these details are stored in the first element in the array with the index name as final_details
so we will get that array, parse that array and fill the details
-->
{assign var="FINAL" value=$ASSOCIATEDPRODUCTS.1.final_details}

   <!-- Product Details Final Total Discount, Tax and Shipping&Hanling  - Starts -->
   <tr valign="top">
	<td width="88%" colspan="2" class="crmTableRow small lineOnTop cblds-t-align_right" align="right"><b>{$APP.LBL_NET_TOTAL}</b></td>
	<td width="12%" id="netTotal" class="crmTableRow small lineOnTop cblds-t-align_right" align="right">0.00</td>
   </tr>

   <tr valign="top">
	<td class="crmTableRow small lineOnTop" width="60%" style="border-right:1px #dadada;">&nbsp;</td>
	<td class="crmTableRow small lineOnTop cblds-t-align_right" align="right">
		(-)&nbsp;<b><a href="javascript:doNothing();" onClick="displayCoords(this,'discount_div_final','discount_final','1')">{$APP.LBL_DISCOUNT}</a>

		<!-- Popup Discount DIV -->
		<div class="discountUI" id="discount_div_final">
			<input type="hidden" id="discount_type_final" name="discount_type_final" value="{$FINAL.discount_type_final}">
			<table width="100%" border="0" cellpadding="5" cellspacing="0" class="small">
			   <tr>
				<td id="discount_div_title_final" nowrap align="left" ></td>
				<td align="right" class="cblds-t-align_right"><img src="{'close.gif'|@vtiger_imageurl:$THEME}" border="0" onClick="fnhide('discount_div_final')" style="cursor:pointer;"></td>
			   </tr>
			   <tr>
				<td align="left" class="lineOnTop"><input type="radio" name="discount_final" checked onclick="setDiscount(this,'_final'); calcGroupTax(); calcTotal();">&nbsp; {$APP.LBL_ZERO_DISCOUNT}</td>
				<td class="lineOnTop">&nbsp;</td>
			   </tr>
			   <tr>
				<td align="left"><input type="radio" name="discount_final" onclick="setDiscount(this,'_final');  calcTotal(); calcGroupTax();" {if isset($FINAL.checked_discount_percentage_final)}{$FINAL.checked_discount_percentage_final}{/if}>&nbsp; % {$APP.LBL_OF_PRICE}</td>
				<td align="right" class="cblds-t-align_right"><input type="text" class="small" size="5" id="discount_percentage_final" name="discount_percentage_final" value="{$FINAL.discount_percentage_final}" {if isset($FINAL.style_discount_percentage_final)}{$FINAL.style_discount_percentage_final}{/if} onBlur="setDiscount(this,'_final'); calcGroupTax(); calcTotal();">&nbsp;%</td>
			   </tr>
			   <tr>
				<td align="left" nowrap><input type="radio" name="discount_final" onclick="setDiscount(this,'_final');  calcTotal(); calcGroupTax();" {if isset($FINAL.checked_discount_amount_final)}{$FINAL.checked_discount_amount_final}{/if}>&nbsp;{$APP.LBL_DIRECT_PRICE_REDUCTION}</td>
				<td align="right" class="cblds-t-align_right"><input type="text" id="discount_amount_final" name="discount_amount_final" size="5" value="{$FINAL.discount_amount_final}" {if isset($FINAL.style_discount_amount_final)}{$FINAL.style_discount_amount_final}{/if} onBlur="setDiscount(this,'_final');  calcGroupTax(); calcTotal();"></td>
			   </tr>
			</table>
		</div>
		<!-- End Div -->

	</td>
	<td id="discountTotal_final" class="crmTableRow small lineOnTop cblds-t-align_right" align="right">{$FINAL.discountTotal_final}</td>
   </tr>

   <!-- Group Tax - starts -->
   <tr id="group_tax_row" valign="top" class="TaxHide">
	<td class="crmTableRow small lineOnTop" style="border-right:1px #dadada;">&nbsp;</td>
	<td class="crmTableRow small lineOnTop cblds-t-align_right" align="right">
		(+)&nbsp;<b><a href="javascript:doNothing();" onClick="displayCoords(this,'group_tax_div','group_tax_div_title','');  calcTotal(); calcGroupTax();" >{$APP.LBL_TAX}</a></b>
			<!-- Pop Div For Group TAX -->
			{assign var="GROUP_TAXES" value=$FINAL.taxes}
			<div class="discountUI" id="group_tax_div">{include file="Inventory/GroupTax.tpl"}</div>
			<!-- End Popup Div Group Tax -->
	</td>
	<td id="tax_final" class="crmTableRow small lineOnTop cblds-t-align_right" align="right">{$FINAL.tax_totalamount}</td>
   </tr>
   <!-- Group Tax - ends -->

{if $SHOW_SHIPHAND_CHARGES}
   <tr valign="top">
	<td class="crmTableRow small" style="border-right:1px #dadada;">&nbsp;</td>
	<td class="crmTableRow small cblds-t-align_right" align="right">
		(+)&nbsp;<b>{$APP.LBL_SHIPPING_AND_HANDLING_CHARGES} </b>
	</td>
	<td class="crmTableRow small cblds-t-align_right" align="right">
		<input id="shipping_handling_charge" name="shipping_handling_charge" type="text" class="small" style="width:75px;text-align:right" align="right" value="{$FINAL.shipping_handling_charge}" onBlur="calcSHTax();">
	</td>
   </tr>
{else}
<input id="shipping_handling_charge" name="shipping_handling_charge" type="hidden" value="0.00">
{/if}
{if !empty($FINAL.sh_taxes)}
   <tr valign="top">
	<td class="crmTableRow small" style="border-right:1px #dadada;">&nbsp;</td>
	<td class="crmTableRow small cblds-t-align_right" align="right">
		(+)&nbsp;<b><a href="javascript:doNothing();" onClick="displayCoords(this,'shipping_handling_div','sh_tax_div_title',''); calcSHTax();" >{$APP.LBL_TAX_FOR_SHIPPING_AND_HANDLING} </a></b>

				<!-- Pop Div For Shipping and Handlin TAX -->
				<div class="discountUI" id="shipping_handling_div">
					<table width="100%" border="0" cellpadding="5" cellspacing="0" class="small">
					   <tr>
						<td id="sh_tax_div_title" colspan="2" nowrap align="left" ></td>
						<td align="right" class="cblds-t-align_right"><img src="{'close.gif'|@vtiger_imageurl:$THEME}" border="0" onClick="fnhide('shipping_handling_div')" style="cursor:pointer;"></td>
					   </tr>

					{foreach item=tax_detail name=sh_loop key=loop_count from=$FINAL.sh_taxes}

					   <tr>
						<td align="left" class="lineOnTop">
							<input type="text" class="small" size="3" name="{$tax_detail.taxname}_sh_percent" id="sh_tax_percentage{$smarty.foreach.sh_loop.iteration}" value="{$tax_detail.percentage}" onBlur="calcSHTax()">&nbsp;%
						</td>
						<td align="center" class="lineOnTop cblds-t-align_center">{$tax_detail.taxlabel}</td>
						<td align="right" class="lineOnTop cblds-t-align_right">
							<input type="text" class="small" size="4" name="{$tax_detail.taxname}_sh_amount" id="sh_tax_amount{$smarty.foreach.sh_loop.iteration}" style="cursor:pointer;" value="0.00" readonly>
						</td>
					   </tr>

					{/foreach}
					<input type="hidden" id="sh_tax_count" value="{$smarty.foreach.sh_loop.iteration}">

					</table>
				</div>
				<!-- End Popup Div for Shipping and Handling TAX -->
	</td>
	<td id="shipping_handling_tax" class="crmTableRow small cblds-t-align_right" align="right">{$FINAL.shtax_totalamount}</td>
   </tr>
{/if}
   <tr valign="top">
	<td class="crmTableRow small" style="border-right:1px #dadada;">&nbsp;</td>
	<td class="crmTableRow small cblds-t-align_right" align="right">
		{$APP.LBL_ADJUSTMENT}
		<select id="adjustmentType" name="adjustmentType" class=small onchange="calcTotal();">
			<option value="+">{$APP.LBL_ADD_ITEM}</option>
			<option value="-">{$APP.LBL_DEDUCT}</option>
		</select>
	</td>
	<td class="crmTableRow small cblds-t-align_right" align="right">
		<input id="adjustment" name="adjustment" type="text" class="small" style="width:75px;text-align:right" align="right" value="{$FINAL.adjustment}" onBlur="calcTotal();">
	</td>
   </tr>
   <tr valign="top">
	<td class="crmTableRow big lineOnTop" style="border-right:1px #dadada;">&nbsp;</td>
	<td class="crmTableRow big lineOnTop cblds-t-align_right" align="right"><b>{$APP.LBL_GRAND_TOTAL}</b></td>
	<td id="grandTotal" name="grandTotal" class="crmTableRow big lineOnTop cblds-t-align_right" align="right">{$FINAL.grandTotal}</td>
   </tr>
</table>

		<input type="hidden" name="totalProductCount" id="totalProductCount" value="{$row_no}">
		<input type="hidden" name="subtotal" id="subtotal" value="">
		<input type="hidden" name="total" id="total" value="">
</td></tr>

{foreach key=row_no item=data from=$ASSOCIATEDPRODUCTS}
	<!-- This is added to call the function calcCurrentTax which will calculate the tax amount from percentage -->
	{if isset($data.taxes)}
	{foreach key=tax_row_no item=tax_data from=$data.taxes}
		{assign var="taxname" value=$tax_data.taxname|cat:"_percentage"|cat:$row_no}
			<script>calcCurrentTax('{$taxname}',{$row_no},{$tax_row_no});</script>
	{/foreach}
	{/if}
	{assign var="entityIndentifier" value='entityType'|cat:$row_no}
	{if $MODULE eq 'Invoice' && $data.$entityIndentifier neq 'Services'}
		<script>stock_alert('{$row_no}');</script>
	{/if}
	<script>rowCnt={$row_no};</script>
{/foreach}

<!-- Added to calculate the tax and total values when page loads -->
<script>
decideTaxDiv();
{if $TAX_TYPE eq 'group'}
calcGroupTax();
{/if}
calcTotal();
calcSHTax();
</script>
<!-- This above div is added to display the tax informations -->
