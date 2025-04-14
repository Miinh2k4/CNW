<!-- CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price FLOAT NOT NULL,
    quantity INT NOT NULL
);
CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    image_path VARCHAR(255),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
); -->
<?php
session_start();

// Kết nối CSDL
$conn = new mysqli("localhost", "root", "", "quanly_sp");
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Tạo bảng nếu chưa có (người dùng)
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
)");

// Xử lý đăng ký
if (isset($_POST['register'])) {
    $username = $_POST['reg_username'];
    $password = $_POST['reg_password'];

    if ($username && $password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $hashed);
        if ($stmt->execute()) {
            echo "<p style='color:green'>Đăng ký thành công!</p>";
        } else {
            echo "<p style='color:red'>Tên người dùng đã tồn tại!</p>";
        }
    }
}

// Xử lý đăng nhập
if (isset($_POST['login'])) {
    $username = $_POST['log_username'];
    $password = $_POST['log_password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = $username;
            setcookie("username", $username, time() + 3600);
            echo "<p style='color:green'>Đăng nhập thành công!</p>";
        } else {
            echo "<p style='color:red'>Sai mật khẩu!</p>";
        }
    } else {
        echo "<p style='color:red'>Tài khoản không tồn tại!</p>";
    }
}

// Đăng xuất
if (isset($_GET['logout'])) {
    session_destroy();
    setcookie("username", "", time() - 3600);
    header("Location: index.php");
    exit();
}

// Tạo session lưu sản phẩm
if (!isset($_SESSION['products'])) {
    $_SESSION['products'] = [];
}

// Xử lý thêm sản phẩm
$errors = [];
if (isset($_POST['add_product'])) {
    define("MAX_IMAGES", 5);

    function isValidPrice($price) {
        return is_numeric($price) && $price > 0;
    }

    $name = trim($_POST['name']);
    $price = $_POST['price'];
    $quantity = $_POST['quantity'];
    $images = $_FILES['images'];

    if (!preg_match("/^[a-zA-Z0-9 ]+$/", $name)) {
        $errors['name'] = "Tên sản phẩm chỉ chứa chữ, số và dấu cách.";
    }
    if (!isValidPrice($price)) {
        $errors['price'] = "Đơn giá phải lớn hơn 0.";
    }
    if (!is_numeric($quantity) || $quantity <= 0) {
        $errors['quantity'] = "Số lượng phải lớn hơn 0.";
    }
    if (count(array_filter($images['name'])) < 3) {
        $errors['images'] = "Cần ít nhất 3 ảnh.";
    }

    $uploaded_images = [];
    if (empty($errors)) {
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        foreach ($images['tmp_name'] as $key => $tmp_name) {
            if ($images['error'][$key] == 0) {
                $img_name = time() . "_" . basename($images['name'][$key]);
                $target = $upload_dir . $img_name;
                if (move_uploaded_file($tmp_name, $target)) {
                    $uploaded_images[] = $target;
                }
            }
        }

        if (count($uploaded_images) >= 3) {
            $_SESSION['products'][] = [
                'name' => $name,
                'price' => $price,
                'quantity' => $quantity,
                'images' => $uploaded_images
            ];
        } else {
            $errors['images'] = "Có lỗi khi tải hình ảnh.";
        }
    }
}

// Xử lý xóa sản phẩm
if (isset($_GET['delete'])) {
    $index = $_GET['delete'];
    if (isset($_SESSION['products'][$index])) {
        // Xóa ảnh từ thư mục
        foreach ($_SESSION['products'][$index]['images'] as $img) {
            if (file_exists($img)) {
                unlink($img);
            }
        }
        // Xóa sản phẩm khỏi session
        array_splice($_SESSION['products'], $index, 1);
        header("Location: index.php");
        exit();
    }
}

// Xử lý sửa sản phẩm
if (isset($_POST['edit_product'])) {
    $index = $_POST['edit_index'];
    $name = trim($_POST['name']);
    $price = $_POST['price'];
    $quantity = $_POST['quantity'];
    $images = $_FILES['images'];
    
    $errors = [];
    
    if (!preg_match("/^[a-zA-Z0-9 ]+$/", $name)) {
        $errors['name'] = "Tên sản phẩm chỉ chứa chữ, số và dấu cách.";
    }
    if (!is_numeric($price) || $price <= 0) {
        $errors['price'] = "Đơn giá phải lớn hơn 0.";
    }
    if (!is_numeric($quantity) || $quantity <= 0) {
        $errors['quantity'] = "Số lượng phải lớn hơn 0.";
    }
    
    if (empty($errors)) {
        // Xử lý ảnh mới nếu có
        $uploaded_images = $_SESSION['products'][$index]['images'];
        if (!empty(array_filter($images['name']))) {
            $upload_dir = "uploads/";
            $new_images = [];
            
            foreach ($images['tmp_name'] as $key => $tmp_name) {
                if ($images['error'][$key] == 0) {
                    $img_name = time() . "_" . basename($images['name'][$key]);
                    $target = $upload_dir . $img_name;
                    if (move_uploaded_file($tmp_name, $target)) {
                        $new_images[] = $target;
                    }
                }
            }
            
            if (count($new_images) >= 3) {
                // Xóa ảnh cũ
                foreach ($uploaded_images as $img) {
                    if (file_exists($img)) {
                        unlink($img);
                    }
                }
                $uploaded_images = $new_images;
            }
        }
        
        // Cập nhật sản phẩm
        $_SESSION['products'][$index] = [
            'name' => $name,
            'price' => $price,
            'quantity' => $quantity,
            'images' => $uploaded_images
        ];
        echo "<p style='color:green'>Cập nhật sản phẩm thành công!</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý sản phẩm</title>
</head>
<body>
    <h2>Đăng ký</h2>
    <form method="POST">
        <input name="reg_username" placeholder="Tên người dùng">
        <input type="password" name="reg_password" placeholder="Mật khẩu">
        <button name="register">Đăng ký</button>
    </form>

    <h2>Đăng nhập</h2>
    <form method="POST">
        <input name="log_username" placeholder="Tên người dùng">
        <input type="password" name="log_password" placeholder="Mật khẩu">
        <button name="login">Đăng nhập</button>
    </form>

    <?php if (isset($_SESSION['user'])): ?>
        <h3>Xin chào, <?= htmlspecialchars($_SESSION['user']) ?> (<a href="?logout=1">Đăng xuất</a>)</h3>

        <h2>Thêm sản phẩm</h2>
        <form method="POST" enctype="multipart/form-data">
            <input name="name" placeholder="Tên" value="<?= $_POST['name'] ?? '' ?>">
            <span style="color:red"> <?= $errors['name'] ?? '' ?> </span><br>

            <input name="price" placeholder="Giá" value="<?= $_POST['price'] ?? '' ?>">
            <span style="color:red"> <?= $errors['price'] ?? '' ?> </span><br>

            <input name="quantity" placeholder="Số lượng" value="<?= $_POST['quantity'] ?? '' ?>">
            <span style="color:red"> <?= $errors['quantity'] ?? '' ?> </span><br>

            <input type="file" name="images[]" multiple>
            <span style="color:red"> <?= $errors['images'] ?? '' ?> </span><br>

            <button name="add_product">Thêm sản phẩm</button>
        </form>

        <h2>Danh sách sản phẩm</h2>
        <ul>
            <?php foreach ($_SESSION['products'] as $index => $p): ?>
                <li>
                    <strong><?= htmlspecialchars($p['name']) ?></strong> - Giá: <?= $p['price'] ?> - SL: <?= $p['quantity'] ?><br>
                    <?php foreach ($p['images'] as $img): ?>
                        <img src="<?= $img ?>" width="100">
                    <?php endforeach; ?>
                    <br>
                    <a href="?delete=<?= $index ?>" onclick="return confirm('Bạn có chắc muốn xóa sản phẩm này?')">Xóa</a>
                    <button onclick="showEditForm(<?= $index ?>)">Sửa</button>

                    <!-- Form sửa sản phẩm (ẩn mặc định) -->
                    <div id="edit-form-<?= $index ?>" style="display:none;">
                        <h3>Sửa sản phẩm</h3>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="edit_index" value="<?= $index ?>">
                            <input name="name" placeholder="Tên" value="<?= htmlspecialchars($p['name']) ?>">
                            <span style="color:red"> <?= $errors['name'] ?? '' ?> </span><br>

                            <input name="price" placeholder="Giá" value="<?= $p['price'] ?>">
                            <span style="color:red"> <?= $errors['price'] ?? '' ?> </span><br>

                            <input name="quantity" placeholder="Số lượng" value="<?= $p['quantity'] ?>">
                            <span style="color:red"> <?= $errors['quantity'] ?? '' ?> </span><br>

                            <input type="file" name="images[]" multiple>
                            <span style="color:red"> <?= $errors['images'] ?? '' ?> </span><br>

                            <button name="edit_product">Cập nhật</button>
                            <button type="button" onclick="hideEditForm(<?= $index ?>)">Hủy</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <script>
        function showEditForm(index) {
            document.getElementById('edit-form-' + index).style.display = 'block';
        }

        function hideEditForm(index) {
            document.getElementById('edit-form-' + index).style.display = 'none';
        }
    </script>
</body>
</html>