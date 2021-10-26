<?php
function execQuery($sql) {
	$result = db_assoc_array(db_query($sql));
	return $result;
}

function execNonQuery($sql) {
	db_query($sql);
	return db_affected_rows();
}

function parseSubject($subj) {
	$matches = array();
    preg_match('/^\[(\d+):(.+)\]\s?(.+)?$/', $subj, $matches);

	$result = array(
		'resource_id' => '',
	    'resource_name' => '',
	    'subject' => '',
	);

	$count_matches = count($matches);
    if ($count_matches > 1) {
        $result['resource_id'] = $matches[1];
		if ($count_matches > 2) $result['resource_name'] = $matches[2];
        if ($count_matches > 3) $result['subject'] = $matches[3];
		return $result;
    }

	return false;
}

function findListItem($items, $resource_id) {
	foreach ($items as $i) {
		$parts = explode(':', $i['value']);
		if (count($parts) > 0) {
			if ($parts[0] == $resource_id) {
				return $i;
			}
		}
	}
	return null;
}

function insertListItem($list_id, $resource_id, $resource_name) {
    $result = array(
        'id'            => 0,
	    'list_id'       => $list_id,
        'status'        => 1,
        'value'         => $resource_id.':'.$resource_name,
        'extra'         => null,
        'sort'          => 1,
	    'properties'    => '[]',
    );

	$lid = db_real_escape($result['list_id']);
    $value = db_real_escape($result['value']);

    execNonQuery("INSERT ost_list_items (list_id, status, value, extra, sort, properties) VALUES ($lid, {$result['status']}, '$value', NULL, {$result['sort']}, '{$result['properties']}')");

	$result['id'] = db_insert_id();

	return $result;
}

function getFormEntryValue($entry_id, $field_id) {
	$eid = db_real_escape($entry_id);
	$fid = db_real_escape($field_id);
	$q = execQuery("SELECT * FROM ost_form_entry_values WHERE entry_id = $eid AND field_id = $fid");
	if (count($q) > 0)
		return $q[0];
	else
		return false;
}

function insertFormEntryValue($entry_id, $field_id, $resource_id, $resource_name) {
	$result = array(
		'entry_id'	=> $entry_id,
		'field_id'	=> $field_id,
		'value'		=> '{"'.$resource_id.'":"'.$resource_name.'"}',
		'value_id'	=> null,
	);

	$value = db_real_escape($result['value']);

	execNonQuery("INSERT ost_form_entry_values (entry_id, field_id, value, value_id) VALUES ($entry_id, $field_id, '$value', NULL)");

	return $result;
}

function updateFormEntryValue($entry_id, $field_id, $item_id, $resource_id, $resource_name) {
	$result = array(
        'entry_id'  => $entry_id,
        'field_id'  => $field_id,
        'value'     => '{"'.$item_id.'":"'.$resource_id.':'.$resource_name.'"}',
        'value_id'  => null,
    );

	$eid = db_real_escape($entry_id);
	$fid = db_real_escape($field_id);
    $value = db_real_escape($result['value']);

    execNonQuery("UPDATE ost_form_entry_values SET value = '$value' WHERE entry_id = $eid AND field_id = $fid");

    return $result;
}

function updateTicketCData($ticket_id, $item_id) {
	$tid = db_real_escape($ticket_id);
	$iid = db_real_escape($item_id);
	$count = execNonQuery("UPDATE ost_ticket__cdata SET resource_id = $iid WHERE ticket_id = $tid");
	return $count;
}

$command = $_GET['command'] ?? false;
$title = "";
$output = "";
$query = false;
$errmsg = false;
$paginate = false;

$resource_tickets_sql = "SELECT t.ticket_id, t.number AS ticket_number, t.created, cd.subject, cd.resource_id, fe.id AS entry_id, fev.field_id, fev.value, fev.field_name"
	. " FROM ost_ticket__cdata cd"
    . " INNER JOIN ost_ticket t ON t.ticket_id = cd.ticket_id"
    . " INNER JOIN ost_form_entry fe ON fe.object_id = cd.ticket_id"
    . " LEFT JOIN (SELECT v.entry_id, v.field_id, v.value, ff.name AS field_name"
        . " FROM ost_form_entry_values v"
        . " INNER JOIN ost_form_field ff ON v.field_id = ff.id"
        . " WHERE ff.name = 'resource_id') fev ON fev.entry_id = fe.id"
    . " WHERE cd.subject LIKE '[%:%] %' AND fe.object_type = 'T'";

// always get the correct field_id;
$field_id = 0;
$field_type = "";
$q = execQuery("SELECT id, type FROM ost_form_field WHERE name = 'resource_id'");
if (count($q) > 0) {
    $field_id = db_real_escape($q[0]['id']);
    $field_type = db_real_escape($q[0]['type']);
}

if (!$field_id) {
    $errmsg .= '<div>Cannot determine field_id of resource_id field.</div>';
}

// always get resource list items
$list_items = null;
$count_list_items = 0;
$list_id = 0;
if ($field_type) {
	$list_items = execQuery("SELECT li.* FROM ost_list l INNER JOIN ost_list_items li ON li.list_id = l.id WHERE CONCAT('list-', l.id) = '$field_type'");
	$count_list_items = count($list_items);
	if ($count_list_items <= 0){
   		$errmsg .= "<div>No list items were found for field_id: $field_id, field_type: $field_type</div>";
	} else {
		// get list_id from first item
		$list_id = db_real_escape($list_items[0]['list_id']);
	}
}

if (!$errmsg) {
	switch ($command) {
		case 'fix-resource-ticket':
			$ticket_id = db_real_escape($_GET['ticket_id'] ?? 0);

	        $title = "Fix Resource Ticket for ticket_id: $ticket_id";

	        if ($ticket_id) {
				$q = execQuery($resource_tickets_sql . " AND t.ticket_id = $ticket_id");

				// make wure there is only one
				if (count($q) != 1) {
					$errmsg = "Cannot find ticket with ticket_id: $ticket_id [count: ".count($q)."]";
					break;
				}

				$ticket = $q[0];
				$subj = $ticket['subject'];
				if ($parsed = parseSubject($subj)) {
					$output .= "<div>subject: $subj</div>";
					$output .= "<div>parsed resource_id: {$parsed['resource_id']}</div>";
					$output .= "<div>parsed resource_name: {$parsed['resource_name']}</div>";
					$output .= "<div>parsed subject: {$parsed['subject']}</div>";

					// first we find or add a list item
					$item = findListItem($list_items, $parsed['resource_id']);
					if (!$item) {
						$item = insertListItem($list_id, $parsed['resource_id'], $parsed['resource_name']);
						$list_items[] = $item;
						$output .= "<div>added list item [id: {$item['id']}, value: {$item['value']}]</div>";
					} else {
						$output .= "<div>found list item [id: {$item['id']}, value: {$item['value']}]<div>";
					}

					// next find or add a form entry value
					$value = getFormEntryValue($ticket['entry_id'], $field_id);
					if (!$value) {
						$value = insertFormEntryValue($ticket['entry_id'], $field_id, $parsed['resource_id'], $parsed['resource_name']);
						$output .= "<div>added form entry value [entry_id: {$ticket['entry_id']}, field_id: $field_id]</div>";
					} else {
						$output .= "<div>found form entry value [entry_id: {$ticket['entry_id']}, field_id: $field_id]</div>";
					}

					// update the form entry value
					$updated_value = updateFormEntryValue($value['entry_id'], $value['field_id'], $item['id'], $parsed['resource_id'], $parsed['resource_name']);
					$output .= "<div>updated form entry value [entry_id: {$updated_value['entry_id']}, field_id: {$updated_value['field_id']}, item_id: {$item['id']}, value: {$updated_value['value']}]</div>";

					// update the ticket cdata
					updateTicketCData($ticket['ticket_id'], $item['id']);
					$output .= "<div>updated ticket cdata [ticket_id: {$ticket['ticket_id']}, entry_id: {$item['id']}]</div>";

					$output .= '<div><a href="?command=fix-resource-tickets">fix resource tickets</a></div>';
				} else {
					$output .= "<div>cannot parse subject: $subj</div>";
				}
	        } else {
	            $errmsg = "Missing required parameter: ticket_id";
	        }

			break;
		case 'fix-resource-tickets':
			$title = 'Fix Resource Tickets';

			$output .= "<div>resource_id field_id: $field_id</div>";
			$output .= "<div>list_items count: $count_list_items</div>";
			$output .= "<div>resource list_id: $list_id<div>";

			$q = execQuery($resource_tickets_sql);
			$count_resource_tickets = count($q);
			$output .= "<div>resource_tickets count: $count_resource_tickets</div>";

			$count_missing_resource_id = 0;
			$count_missing_entry_value = 0;
			$count_added_list_items = 0;
			$count_added_entry_values = 0;

			foreach ($q as $r) {
				$tid = db_real_escape($r['ticket_id']);

				// get subject parts: resource_id, resource_name, and (real) subject
				$parsed = parseSubject($r['subject']);

				$item = findListItem($list_items, $parsed['resource_id']);

				if (!$item) {
					// add the list item
					$item = insertListItem($list_id, $parsed['resource_id'], $parsed['resource_name']);

					// must add it to the array or it will be re-added
					$list_items[] = $item;

					$count_added_list_items += 1;
				}

				if (!$item) {
					$output .= "<div>cannot find or add list_item for ticket_id $tid [subject: {$r['subject']}]</div>";
					continue;
				}

				$item_id = db_real_escape($item['id']);

				// first check for cdata resource_id
				if ($r['resource_id'] == null){
					// the resource_id column in ost_ticket__cdata is the list item id
					updateTicketCData($tid, $item_id);
					$count_missing_resource_id += 1;
				}

				// next check form entry value
				if ($r['value'] == null) {
					// try to get the form_entry_value
					$entry_id = $r['entry_id'];
					$value = getFormEntryValue($entry_id, $field_id);

					if ($value) {
						// found it, update
						updateFormEntryValue($value['entry_id'], $$value['field_id'], $item_id, $parsed['resource_id'], $parsed['resource_name']);
					} else {
						// not found, add it
						insertFormEntryValue($entry_id, $field_id, $parsed['resource_id'], $parsed['resource_name']);
						$count_added_entry_values += 1;
					}

					$count_missing_entry_value += 1;
				}
			}

			$output .= "<div>missing_resource_id count: $count_missing_resource_id</div>";
			$output .= "<div>missing_entry_value count: $count_missing_entry_value</div>";
			$output .= "<div>added_list_items count: $count_added_list_items</div>";
			$output .= "<div>added_entry_values count: $count_added_entry_values</div>";

			// return any tickets where cdata resource_id does not match form_entry_value
			$query = execQuery($resource_tickets_sql." AND fev.value NOT LIKE CONCAT('{\"', cd.resource_id, '\":\"%\"}')");

			foreach ($query as &$r) {
				$id = db_real_escape($r['ticket_id']);
				$r['ticket_id'] = '<a href="?command=fix-resource-ticket&ticket_id='.$id.'" title="Fix Resource Ticket ['.$id.']">'.$id.'</a>';
			}

			break;
		case 'get-resource-tickets':
			$paginate = true;
			$skip = db_real_escape($_GET['skip'] ?? 0);
			$limit = db_real_escape($_GET['limit'] ?? 10);

			$title = 'Resource Tickets (ordered by created descending)';

			$sql = $resource_tickets_sql . " ORDER BY t.created DESC LIMIT $skip, $limit";

			$query = execQuery($sql);

			foreach ($query as &$r) {
				$subj = $r['subject'];

				$parsed = parseSubject($subj);

				$r['parsed_resource_id'] = $parsed['resource_id'];
				$r['parsed_resource_name'] = $parsed['resource_name'];
				$r['parsed_subject'] = $parsed['subject'];

	            $id = db_real_escape($r['ticket_id']);
	            $r['ticket_id'] = '<a href="?command=get-form-entry&ticket_id='.$id.'" title="View Form Entries">'.$id.'</a>';
	        }
			break;
		case 'get-ticket':
			$number = db_real_escape($_GET['number'] ?? 0);

			$title = 'Ticket #'.$number;

			if ($number) {
				$query = execQuery("SELECT t.ticket_id, t.number, t.ip_address, t.source, t.dept_id, t.user_id, t.staff_id, t.status_id, t.lastupdate, t.created FROM ost_ticket t WHERE t.number = $number");
				foreach ($query as &$r) {
					$id = db_real_escape($r['ticket_id']);
					$r['ticket_id'] = '<a href="?command=get-form-entry&ticket_id='.$id.'" title="View Form Entries">'.$id.'</a>';
				}
			} else {
				$errmsg = "Missing required parameter: number";
			}

			break;
		case 'get-form-entry':
			$ticket_id = db_real_escape($_GET['ticket_id'] ?? 0);

			$title = "Form Entry for ticket_id: $ticket_id";

			if ($ticket_id) {
				$query = execQuery("SELECT fe.*, t.number as ticket_number FROM ost_form_entry fe INNER JOIN ost_ticket t ON fe.object_id = t.ticket_id WHERE object_type = 'T' AND object_id = $ticket_id");
				foreach ($query as &$r) {
					$id = db_real_escape($r['id']);
					$r['id'] = '<a href="?command=get-form-entry-values&entry_id='.$id.'" title="View Form Entry Values">'.$id.'</a>';
					$number = db_real_escape($r['ticket_number']);
					$r['ticket_number'] = '<a href="?command=get-ticket&number='.$number.'" title="View Ticket">'.$number.'</a>';
				}
			} else {
				$errmsg = "Missing required parameter: ticket_id";
			}

			break;
		case 'get-form-entry-values':
			$entry_id = db_real_escape($_GET['entry_id'] ?? 0);

			$title = "Form Entry Values for entry_id: $entry_id";

	        if ($entry_id) {
	            $query = execQuery("SELECT fev.entry_id, fev.field_id, f.name AS field_name, fev.value, fev.value_id, fe.object_id AS ticket_id FROM ost_form_entry_values fev INNER JOIN ost_form_entry fe ON fe.id = fev.entry_id INNER JOIN ost_form_field f ON f.id = fev.field_id WHERE entry_id = $entry_id");
				foreach ($query as &$r) {
					$id = db_real_escape($r['ticket_id']);
					$r['ticket_id'] = '<a href="?command=get-form-entry&ticket_id='.$id.'" title="View Form Entires"">'.$id.'</a>';
				}
	        } else {
	            $errmsg = "Missing required parameter: entry_id";
			}

			break;
		case 'get-irregular-form-entry-values':
			$sql = "SELECT * FROM ost_form_entry_values WHERE field_id = 36 AND NOT REGEXP_LIKE(value, '^\\\\{\"[[:digit:]]+\":\"[[:digit:]]+:.+\"\\\\}$', 'i')";
			$query = execQuery($sql);

			if (count($query) == 0)
				$output .= "<div>no irregular form entry values were found</div>";

			break;
		case 'get-irregular-list-items':
			$sql = "SELECT * FROM ost_list_items WHERE list_id = $list_id AND NOT REGEXP_LIKE(value, '^[[:digit:]]+:.+$', 'i')";
			$query = execQuery($sql);

			if (count($query) == 0)
                $output .= "<div>no irregular list items were found</div>";

			break;
	}
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">

	<style>
		.output {
			font-family: Consolas, 'Courier New', monospace;
			padding: 10px;
			border: solid 1px #ccc;
			border-radius: 4px;
			background-color: #f7f7f7;
		}
	</style>

    <title>LNF Data</title>
</head>
<body>
	<div class="container-fluid mt-3">
		<h4>LNF Data</h4>

		<hr>

		<div>
			<a href="?command=get-resource-tickets">get-resource-tickets</a>
			| <a href="?command=fix-resource-tickets">fix-resource-tickets</a>
			| <a href="?command=get-irregular-form-entry-values">get-irregular-form-entry-values</a>
			| <a href="?command=get-irregular-list-items">get-irregular-list-items</a>
		</div>

		<hr>

		<?php if ($errmsg): ?>
		<div class="alert alert-danger" role="alert">
			<?=$errmsg?>
		</div>
		<?php endif; ?>

		<?php if ($title): ?>
		<h5><?=$title?></h5>
		<?php endif; ?>

		<?php if ($output): ?>
		<pre class="output"><?=$output?></pre>
		<?php endif; ?>

		<?php if ($paginate): ?>
		<a href="?command=<?=$command?>&skip=0&limit=<?=$limit?>">first</a>
		| <a href="?command=<?=$command?>&skip=<?=max($skip-$limit,0)?>&limit=<?=$limit?>">prev</a>
		| <a href="?command=<?=$command?>&skip=<?=($skip+$limit)?>&limit=<?=$limit?>">next</a>
		<?php endif; ?>

		<?php if ($query): ?>
		<table class="table">
			<thead>
				<tr>
					<?php foreach ($query[0] as $k=>$v): ?>
					<th><?=$k?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach($query as $row): ?>
				<tr>
					<?php foreach ($row as $k=>$v): ?>
					<td><?=$v?></td>
					<?php endforeach; ?>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>

	<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
	<script>
	</script>
  </body>
</html>
