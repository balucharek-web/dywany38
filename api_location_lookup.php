<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$loc = $data["location"] ?? "";
$store = $data["store_id"] ?? 0;

// SQL identyczny format jak api_search.php
require "db.php";
$stmt = $db->prepare("SELECT reference FROM products WHERE location = ? AND store_id = ?");
$stmt->execute([$loc, $store]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$row){
    echo json_encode(["success"=>false]);
    exit;
}

echo json_encode([
    "success"=>true,
    "ref"=>$row["reference"]
]);
