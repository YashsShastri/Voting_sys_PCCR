<?php
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');

$db  = getDB();
$eid = (int)($_GET['election_id'] ?? 0);
$type = $_GET['type'] ?? 'results';

if ($type === 'logs') {
    // Export audit logs
    $logs = $db->query("SELECT l.id, u.email, l.action, l.details, l.ip_address, l.timestamp FROM logs l LEFT JOIN users u ON l.user_id=u.id ORDER BY l.timestamp DESC")->fetchAll();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','User','Action','Details','IP','Timestamp']);
    foreach ($logs as $log) fputcsv($out, $log);
    fclose($out); exit;
}

if (!$eid) { flash('error','Invalid election.'); header('Location: elections.php'); exit; }

// Results CSV export
$election = $db->prepare("SELECT * FROM elections WHERE id=?");
$election->execute([$eid]);
$election = $election->fetch();

if (!$election) { flash('error','Election not found.'); header('Location: elections.php'); exit; }

if ($type === 'results') {
    $rows = $db->prepare("SELECT c.name as candidate, COUNT(v.id) as votes FROM candidates c LEFT JOIN votes v ON v.candidate_id=c.id AND v.election_id=? AND v.is_valid=1 WHERE c.election_id=? GROUP BY c.id ORDER BY votes DESC");
    $rows->execute([$eid, $eid]);
    $rows = $rows->fetchAll();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="results_' . preg_replace('/[^a-z0-9]/i','_',$election['title']) . '_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Candidate', 'Votes']);
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out); exit;
} elseif ($type === 'participation') {
    $rows = $db->prepare("SELECT s.name, s.roll_no, s.division, CASE WHEN v.id IS NOT NULL THEN 'Yes' ELSE 'No' END as voted FROM students s LEFT JOIN votes v ON v.student_id=s.id AND v.election_id=? AND v.is_valid=1 WHERE s.verified=1 ORDER BY s.name");
    $rows->execute([$eid]);
    $rows = $rows->fetchAll();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="participation_' . preg_replace('/[^a-z0-9]/i','_',$election['title']) . '_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Roll No', 'Division', 'Voted?']);
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out); exit;
}
flash('error', 'Invalid export type.'); header('Location: elections.php'); exit;
