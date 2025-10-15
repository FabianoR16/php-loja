// index.php - Loja simples em PHP (arquivo único)
// Como usar: coloque este arquivo em uma pasta no seu servidor PHP (ou use: php -S localhost:8000)
// O site usa SQLite (arquivo shop.db) e cria/insere dados automaticamente na primeira execução.

session_start();
// --- Inicializar DB (SQLite) ---
$dbFile = __DIR__ . '/shop.db';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function init_db($pdo){
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        price REAL NOT NULL,
        stock INTEGER NOT NULL,
        image TEXT
    )");

    // Inserir produtos de exemplo se tabela vazia
    $count = $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    if($count == 0){
        $products = [
            ['Camisa Estilosa','Camisa 100% algodão, várias cores',49.90,10,'https://via.placeholder.com/300x200?text=Camisa'],
            ['Tênis Confort','Tênis para corrida e dia a dia',199.90,5,'https://via.placeholder.com/300x200?text=Tenis'],
            ['Boné Moderno','Boné com ajuste traseiro',39.90,20,'https://via.placeholder.com/300x200?text=Bone'],
        ];
        $stmt = $pdo->prepare('INSERT INTO products (title,description,price,stock,image) VALUES (?,?,?,?,?)');
        foreach($products as $p){ $stmt->execute($p); }
    }
}
init_db($pdo);

// --- Funções de carrinho ---
if(!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

function add_to_cart($product_id, $qty=1){
    $id = (int)$product_id;
    $qty = max(1,(int)$qty);
    if(isset($_SESSION['cart'][$id])){
        $_SESSION['cart'][$id] += $qty;
    } else {
        $_SESSION['cart'][$id] = $qty;
    }
}

function remove_from_cart($product_id){
    $id = (int)$product_id;
    if(isset($_SESSION['cart'][$id])) unset($_SESSION['cart'][$id]);
}

function update_cart($updates){
    foreach($updates as $id => $qty){
        $id = (int)$id; $qty = (int)$qty;
        if($qty <= 0) unset($_SESSION['cart'][$id]);
        else $_SESSION['cart'][$id] = $qty;
    }
}

// --- Rotas / Actions ---
$action = $_REQUEST['action'] ?? '';
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if($action === 'add'){
        add_to_cart($_POST['product_id'] ?? 0, $_POST['qty'] ?? 1);
        header('Location: ?page=cart'); exit;
    }
    if($action === 'remove'){
        remove_from_cart($_POST['product_id'] ?? 0);
        header('Location: ?page=cart'); exit;
    }
    if($action === 'update'){
        update_cart($_POST['qty'] ?? []);
        header('Location: ?page=cart'); exit;
    }
    if($action === 'checkout'){
        // Simula finalização: reduz estoque e limpa carrinho
        try{
            $pdo->beginTransaction();
            foreach($_SESSION['cart'] as $pid => $qty){
                $stmt = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
                $stmt->execute([$pid]);
                $stock = $stmt->fetchColumn();
                if($stock === false) continue;
                if($stock < $qty){
                    throw new Exception("Estoque insuficiente para o produto #$pid");
                }
                $stmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
                $stmt->execute([$qty,$pid]);
            }
            $pdo->commit();
            $_SESSION['last_order'] = ['date' => date('c'), 'items' => $_SESSION['cart']];
            $_SESSION['cart'] = [];
            header('Location: ?page=thanks'); exit;
        }catch(Exception $e){
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }