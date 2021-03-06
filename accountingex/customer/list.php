<?php
/* Copyright (C) 2013-2014 Olivier Geffroy      <jeff@jeffinfo.com>
 * Copyright (C) 2013-2014 Alexandre Spangaro	<alexandre.spangaro@gmail.com>
 * Copyright (C) 2014      Ari Elbaz (elarifr)	<github@accedinfo.com>
 * Copyright (C) 2013-2014 Florian Henry		<florian.henry@open-concept.pro>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file		accountingex/customer/list.php
 * \ingroup		Accounting Expert
 * \brief		Page de ventilation des lignes de facture clients
 */

// Dolibarr environment
$res = @include ("../main.inc.php");
if (! $res && file_exists("../main.inc.php"))
	$res = @include ("../main.inc.php");
if (! $res && file_exists("../../main.inc.php"))
	$res = @include ("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php"))
	$res = @include ("../../../main.inc.php");
if (! $res)
	die("Include of main fails");

	// Class
dol_include_once("/compta/facture/class/facture.class.php");
dol_include_once("/product/class/product.class.php");
dol_include_once("/accountingex/class/html.formventilation.class.php");

// Langs
$langs->load("compta");
$langs->load("bills");
$langs->load("main");
$langs->load("accountingex@accountingex");

$action = GETPOST('action');
$codeventil = GETPOST('codeventil', 'array');
$mesCasesCochees = GETPOST('mesCasesCochees', 'array');

// Security check
if ($user->societe_id > 0)
	accessforbidden();
if (! $user->rights->accountingex->access)
	accessforbidden();

$formventilation = new FormVentilation($db);

llxHeader('', $langs->trans("Ventilation"));

print  '<script type="text/javascript">
			$(function () {
				$(\'#select-all\').click(function(event) {
				    // Iterate each checkbox
				    $(\':checkbox\').each(function() {
				    	this.checked = true;
				    });
			    });
			    $(\'#unselect-all\').click(function(event) {
				    // Iterate each checkbox
				    $(\':checkbox\').each(function() {
				    	this.checked = false;
				    });
			    });
			});
			 </script>';

/*
 * Action
*/

if ($action == 'ventil') {
	print '<div><font color="red">' . $langs->trans("Processing") . '...</font></div>';
	if (! empty($codeventil) && ! empty($mesCasesCochees)) {
		print '<div><font color="red">' . count($mesCasesCochees) . ' ' . $langs->trans("SelectedLines") . '</font></div>';
		$mesCodesVentilChoisis = $codeventil;
		$cpt = 0;
		foreach ( $mesCasesCochees as $maLigneCochee ) {
			// print '<div><font color="red">id selectionnee : '.$monChoix."</font></div>";
			$maLigneCourante = explode("_", $maLigneCochee);
			$monId = $maLigneCourante[0];
			$monNumLigne = $maLigneCourante[1];
			$monCompte = $mesCodesVentilChoisis[$monNumLigne];

			$sql = " UPDATE " . MAIN_DB_PREFIX . "facturedet";
			$sql .= " SET fk_code_ventilation = " . $monCompte;
			$sql .= " WHERE rowid = " . $monId;

			dol_syslog("/accountingex/customer/list.php sql=" . $sql, LOG_DEBUG);
			if ($db->query($sql)) {
				print '<div><font color="green">' . $langs->trans("Lineofinvoice") . ' ' . $monId . ' ' . $langs->trans("VentilatedinAccount") . ' : ' . $monCompte . '</font></div>';
			} else {
				print '<div><font color="red">' . $langs->trans("ErrorDB") . ' : ' . $langs->trans("Lineofinvoice") . ' ' . $monId . ' ' . $langs->trans("NotVentilatedinAccount") . ' : ' . $monCompte . '<br/> <pre>' . $sql . '</pre></font></div>';
			}

			$cpt ++;
		}
	} else {
		print '<div><font color="red">' . $langs->trans("AnyLineVentilate") . '</font></div>';
	}
	print '<div><font color="red">' . $langs->trans("EndProcessing") . '</font></div>';
}

/*
 * Liste des comptes
 */

$sqlCompte = "SELECT a.rowid, a.account_number, a.label, a.fk_pcg_version";
$sqlCompte .= " , s.rowid, s.pcg_version";
$sqlCompte .= " FROM " . MAIN_DB_PREFIX . "accountingaccount as a, " . MAIN_DB_PREFIX . "accounting_system as s";
$sqlCompte .= " WHERE a.fk_pcg_version = s.pcg_version AND s.rowid=" . $conf->global->CHARTOFACCOUNTS;
$sqlCompte .= " AND a.active = '1'";
$sqlCompte .= " ORDER BY a.account_number ASC";

$resultCompte = $db->query($sqlCompte);
$cgs = array ();
$cgn = array ();
if ($resultCompte) {
	$numCompte = $db->num_rows($resultCompte);
	$iCompte = 0;

	while ( $iCompte < $numCompte ) {
		$rowCompte = $db->fetch_row($resultCompte);
		$cgs[$rowCompte[0]] = $rowCompte[1] . ' ' . $rowCompte[2];
		$cgn[$rowCompte[1]] = $rowCompte[0];
		$iCompte ++;
	}
}

/*
 * Customer Invoice lines
 */
$page = GETPOST('page');
if ($page < 0)
	$page = 0;

if (! empty($conf->global->ACCOUNTINGEX_LIMIT_LIST_VENTILATION)) {
	$limit = $conf->global->ACCOUNTINGEX_LIMIT_LIST_VENTILATION;
} else if ($conf->global->ACCOUNTINGEX_LIMIT_LIST_VENTILATION <= 0) {
	$limit = $conf->liste_limit;
} else {
	$limit = $conf->liste_limit;
}

$offset = $limit * $page;

$sql = "SELECT f.facnumber, f.rowid as facid, l.fk_product, l.description, l.total_ht, l.rowid, l.fk_code_ventilation,";
$sql .= " p.rowid as product_id, p.ref as product_ref, p.label as product_label, l.product_type as type, p.accountancy_code_sell as code_sell";
$sql .= " , aa.rowid as aarowid";
$sql .= " FROM " . MAIN_DB_PREFIX . "facture as f";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "facturedet as l ON f.rowid = l.fk_facture";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "product as p ON p.rowid = l.fk_product";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "accountingaccount as aa ON p.accountancy_code_sell = aa.account_number";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "accounting_system as accsys ON accsys.pcg_version = aa.fk_pcg_version";
$sql .= " WHERE f.fk_statut > 0 AND fk_code_ventilation <= 0";
//$sql .= " AND (accsys.rowid='" . $conf->global->CHARTOFACCOUNTS . "' OR p.accountancy_code_sell IS NULL OR p.accountancy_code_sell ='')";
if (! empty($conf->multicompany->enabled)) {
	$sql .= " AND f.entity = '" . $conf->entity . "'";
}

$sql .= " ORDER BY l.rowid";
if ($conf->global->ACCOUNTINGEX_LIST_SORT_VENTILATION_TODO > 0) {
	$sql .= " DESC ";
}
$sql .= $db->plimit($limit + 1, $offset);

dol_syslog("/accountingex/customer/list.php sql=" . $sql, LOG_DEBUG);
$result = $db->query($sql);
if ($result) {
	$num_lignes = $db->num_rows($result);
	$i = 0;

	// TODO : print_barre_liste always use $conf->liste_limit and do not care about custom limit in list...
	print_barre_liste($langs->trans("InvoiceLines"), $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, '', $num_lignes);

	print '<br><b>' . $langs->trans("DescVentilTodoCustomer") . '</b></br>';

	print '<form action="' . $_SERVER["PHP_SELF"] . '" method="POST">' . "\n";
	print '<input type="hidden" name="action" value="ventil">';

	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre"><td>' . $langs->trans("Invoice") . '</td>';
	print '<td>' . $langs->trans("Ref") . '</td>';
	print '<td>' . $langs->trans("Label") . '</td>';
	print '<td>' . $langs->trans("Description") . '</td>';
	print '<td align="right">' . $langs->trans("Amount") . '</td>';
	print '<td align="right">' . $langs->trans("AccountAccounting") . '</td>';
	print '<td align="center">' . $langs->trans("IntoAccount") . '</td>';
	print '<td align="center">'.$langs->trans("Ventilate").'<BR><label id="select-all">'.$langs->trans('All').'</label>/<label id="unselect-all">'.$langs->trans('None').'</label>'.'</td>';
	print '</tr>';

	$facture_static = new Facture($db);
	$product_static = new Product($db);
	$form = new Form($db);

	$var = True;
	while ( $i < min($num_lignes, $limit) ) {
		$objp = $db->fetch_object($result);
		$var = ! $var;

		// product_type: 0 = service ? 1 = product
		// if product does not exist we use the value of product_type provided in facturedet to define if this is a product or service
		// issue : if we change product_type value in product DB it should differ from the value stored in facturedet DB !
		$code_sell_notset = '';

		if (empty($objp->code_sell)) {
			$code_sell_notset = 'color:red';
		} else {
			$code_sell_notset = 'color:blue';
		}

		if ($objp->type == 1) {
			$objp->code_sell2 = (! empty($conf->global->COMPTA_SERVICE_SOLD_ACCOUNT) ? $conf->global->COMPTA_SERVICE_SOLD_ACCOUNT : $langs->trans("CodeNotDef"));
		} else {
			$objp->code_sell2 = (! empty($conf->global->COMPTA_PRODUCT_SOLD_ACCOUNT) ? $conf->global->COMPTA_PRODUCT_SOLD_ACCOUNT : $langs->trans("CodeNotDef"));
		}

		if ($objp->type == 1) {
			$objp->code_sell2 = (! empty($conf->global->COMPTA_SERVICE_SOLD_ACCOUNT) ? $conf->global->COMPTA_SERVICE_SOLD_ACCOUNT : $langs->trans("CodeNotDef"));
		} else {
			$objp->code_sell2 = (! empty($conf->global->COMPTA_PRODUCT_SOLD_ACCOUNT) ? $conf->global->COMPTA_PRODUCT_SOLD_ACCOUNT : $langs->trans("CodeNotDef"));
		}

		print "<tr $bc[$var]>";

		// Ref facture
		$facture_static->ref = $objp->facnumber;
		$facture_static->id = $objp->facid;
		print '<td>' . $facture_static->getNomUrl(1) . '</td>';

		// Ref produit
		$product_static->ref = $objp->product_ref;
		$product_static->id = $objp->product_id;
		$product_static->type = $objp->type;
		print '<td>';
		if ($product_static->id)
			print $product_static->getNomUrl(1);
		else
			print '&nbsp;';
		print '</td>';

		print '<td>' . dol_trunc($objp->product_label, 24) . '</td>';
		print '<td>' . nl2br(dol_trunc($objp->description, 32)) . '</td>';

		print '<td align="right">';
		print price($objp->total_ht);
		print '</td>';

		print '<td align="center" style="' . $code_sell_notset . '">';
		print $objp->code_sell2;
		print '</td>';

		// Colonne choix du compte
		print '<td align="center">';
		//print $formventilation->select_account($objp->aarowid, 'codeventil[]', 1,array(),$objp->code_sell2);
		print $form->selectarray("codeventil[]", $cgs, $cgn[$objp->code_sell2]);
		print '</td>';

		// Colonne choix ligne a ventiler
		print '<td align="center">';
		print '<input type="checkbox" name="mesCasesCochees[]" value="' . $objp->rowid . "_" . $i . '"' . ($objp->code_sell ? "checked" : "") . '/>';
		print '</td>';

		print '</tr>';
		$i ++;
	}

	print '</table>';
	print '<br>';
	print '<div><center><input type="submit" class="butAction" value="' . $langs->trans("Ventilate") . '"></center></div>';

	print '</form>';
} else {
	print $db->error();
}

$db->close();
llxFooter();
