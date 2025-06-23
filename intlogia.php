<?php
/*
 * سكربت انتلوجيا - نظام إدارة المحتوى للمقالات
 * الإصدار: 1.0
 * تاريخ الإصدار: 2023-10-15
 * المطور: [اسمك]
 */

// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_USER', 'username');
define('DB_PASS', 'password');
define('DB_NAME', 'antologia_db');

// الاتصال بقاعدة البيانات
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8'");
} catch(PDOException $e) {
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// إنشاء الجداول إذا لم تكن موجودة
function createTables() {
    global $pdo;
    
    $articlesTable = "CREATE TABLE IF NOT EXISTS articles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        author_id INT NOT NULL,
        category_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
        slug VARCHAR(255) UNIQUE,
        meta_description VARCHAR(255),
        featured_image VARCHAR(255)
    )";
    
    $categoriesTable = "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        slug VARCHAR(100) UNIQUE
    )";
    
    $usersTable = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'editor', 'author', 'subscriber') DEFAULT 'author',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        bio TEXT,
        avatar VARCHAR(255)
    )";
    
    try {
        $pdo->exec($articlesTable);
        $pdo->exec($categoriesTable);
        $pdo->exec($usersTable);
    } catch(PDOException $e) {
        die("خطأ في إنشاء الجداول: " . $e->getMessage());
    }
}

// استدعاء وظيفة إنشاء الجداول
createTables();

// نموذج المستخدم
class User {
    public static function register($username, $email, $password) {
        global $pdo;
        
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        return $stmt->execute([$username, $email, $hashedPassword]);
    }
    
    public static function login($username, $password) {
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
        return false;
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public static function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    public static function isEditor() {
        return isset($_SESSION['role']) && ($_SESSION['role'] === 'editor' || $_SESSION['role'] === 'admin');
    }
    
    public static function isAuthor() {
        return isset($_SESSION['role']) && ($_SESSION['role'] === 'author' || $_SESSION['role'] === 'editor' || $_SESSION['role'] === 'admin');
    }
}

// نموذج المقالات
class Article {
    public static function create($title, $content, $authorId, $categoryId = null, $status = 'draft', $metaDescription = '', $featuredImage = '') {
        global $pdo;
        
        $slug = self::generateSlug($title);
        $stmt = $pdo->prepare("INSERT INTO articles (title, content, author_id, category_id, status, slug, meta_description, featured_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$title, $content, $authorId, $categoryId, $status, $slug, $metaDescription, $featuredImage]);
    }
    
    public static function update($id, $title, $content, $categoryId = null, $status = 'draft', $metaDescription = '', $featuredImage = '') {
        global $pdo;
        
        $stmt = $pdo->prepare("UPDATE articles SET title = ?, content = ?, category_id = ?, status = ?, meta_description = ?, featured_image = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$title, $content, $categoryId, $status, $metaDescription, $featuredImage, $id]);
    }
    
    public static function delete($id) {
        global $pdo;
        
        $stmt = $pdo->prepare("DELETE FROM articles WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public static function getById($id) {
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT articles.*, users.username as author_name FROM articles LEFT JOIN users ON articles.author_id = users.id WHERE articles.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public static function getAll($status = 'published', $limit = 10, $offset = 0) {
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT articles.*, users.username as author_name FROM articles LEFT JOIN users ON articles.author_id = users.id WHERE status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$status, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    public static function getByCategory($categoryId, $status = 'published', $limit = 10, $offset = 0) {
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT articles.*, users.username as author_name FROM articles LEFT JOIN users ON articles.author_id = users.id WHERE category_id = ? AND status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$categoryId, $status, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    public static function getByAuthor($authorId, $status = 'published', $limit = 10, $offset = 0) {
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT articles.*, users.username as author_name FROM articles LEFT JOIN users ON articles.author_id = users.id WHERE author_id = ? AND status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$authorId, $status, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    public static function generateSlug($title) {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', "-", $slug);
        return $slug;
    }
}

// نموذج التصنيفات
class Category {
    public static function create($name, $description = '') {
        global $pdo;
        
        $slug = Article::generateSlug($name);
        $stmt = $pdo->prepare("INSERT INTO categories (name, description, slug) VALUES (?, ?, ?)");
        return $stmt->execute([$name, $description, $slug]);
    }
    
    public static function getAll() {
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public static function getById($id) {
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}

// بدء الجلسة
session_start();

// التوجيه الأساسي
$request = $_SERVER['REQUEST_URI'];
$basePath = '/antologia';

switch (str_replace($basePath, '', $request)) {
    case '/':
        $articles = Article::getAll();
        include 'views/home.php';
        break;
    case '/article':
        $id = $_GET['id'] ?? 0;
        $article = Article::getById($id);
        include 'views/article.php';
        break;
    case '/category':
        $id = $_GET['id'] ?? 0;
        $articles = Article::getByCategory($id);
        $category = Category::getById($id);
        include 'views/category.php';
        break;
    case '/author':
        $id = $_GET['id'] ?? 0;
        $articles = Article::getByAuthor($id);
        include 'views/author.php';
        break;
    case '/login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'];
            $password = $_POST['password'];
            if (User::login($username, $password)) {
                header("Location: $basePath/dashboard");
                exit;
            } else {
                $error = "اسم المستخدم أو كلمة المرور غير صحيحة";
            }
        }
        include 'views/login.php';
        break;
    case '/register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'];
            $email = $_POST['email'];
            $password = $_POST['password'];
            if (User::register($username, $email, $password)) {
                header("Location: $basePath/login");
                exit;
            } else {
                $error = "حدث خطأ أثناء التسجيل";
            }
        }
        include 'views/register.php';
        break;
    case '/dashboard':
        if (!User::isLoggedIn()) {
            header("Location: $basePath/login");
            exit;
        }
        
        if (User::isAuthor()) {
            $articles = Article::getByAuthor($_SESSION['user_id'], 'draft');
            $publishedArticles = Article::getByAuthor($_SESSION['user_id'], 'published');
        } elseif (User::isEditor() || User::isAdmin()) {
            $articles = Article::getAll('draft');
            $publishedArticles = Article::getAll('published');
        }
        
        include 'views/dashboard.php';
        break;
    case '/dashboard/new-article':
        if (!User::isAuthor()) {
            header("Location: $basePath/dashboard");
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'];
            $content = $_POST['content'];
            $categoryId = $_POST['category_id'] ?? null;
            $status = $_POST['status'] ?? 'draft';
            $metaDescription = $_POST['meta_description'] ?? '';
            
            if (Article::create($title, $content, $_SESSION['user_id'], $categoryId, $status, $metaDescription)) {
                header("Location: $basePath/dashboard");
                exit;
            } else {
                $error = "حدث خطأ أثناء إنشاء المقال";
            }
        }
        
        $categories = Category::getAll();
        include 'views/new-article.php';
        break;
    case '/dashboard/edit-article':
        if (!User::isAuthor()) {
            header("Location: $basePath/dashboard");
            exit;
        }
        
        $id = $_GET['id'] ?? 0;
        $article = Article::getById($id);
        
        if (!$article || ($article['author_id'] != $_SESSION['user_id'] && !User::isEditor())) {
            header("Location: $basePath/dashboard");
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'];
            $content = $_POST['content'];
            $categoryId = $_POST['category_id'] ?? null;
            $status = $_POST['status'] ?? 'draft';
            $metaDescription = $_POST['meta_description'] ?? '';
            
            if (Article::update($id, $title, $content, $categoryId, $status, $metaDescription)) {
                header("Location: $basePath/dashboard");
                exit;
            } else {
                $error = "حدث خطأ أثناء تحديث المقال";
            }
        }
        
        $categories = Category::getAll();
        include 'views/edit-article.php';
        break;
    case '/dashboard/categories':
        if (!User::isAdmin()) {
            header("Location: $basePath/dashboard");
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = $_POST['name'];
            $description = $_POST['description'] ?? '';
            
            if (Category::create($name, $description)) {
                header("Location: $basePath/dashboard/categories");
                exit;
            } else {
                $error = "حدث خطأ أثناء إنشاء التصنيف";
            }
        }
        
        $categories = Category::getAll();
        include 'views/categories.php';
        break;
    case '/logout':
        session_destroy();
        header("Location: $basePath/");
        exit;
        break;
    default:
        http_response_code(404);
        include 'views/404.php';
        break;
}
?>
