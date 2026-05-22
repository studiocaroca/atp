<?php
$servername = "localhost"; // Asegúrate de que este sea el nombre de tu servidor de base de datos
$username = "u194628372_nicolaspiaggio";
$password = "Felipe.2611";
$dbname = "u194628372_cvs";

        if (isset($_POST['submit']) && isset($_FILES['file'])) {
            $file = $_FILES['file'];
            $fileName = $file['name'];
            $fileTmpName = $file['tmp_name'];
            $fileType = $file['type'];
            $fileError = $file['error'];
            $fileSize = $file['size'];
        
            if ($fileError === 0) {
                $fileContent = file_get_contents($fileTmpName);
        
                // Crear conexión
                $conn = new mysqli($servername, $username, $password, $dbname);
        
                if ($conn->connect_error) {
                    die("Connection failed: " . $conn->connect_error);
                }
        
                $stmt = $conn->prepare("INSERT INTO cv_uploads (filename, filedata) VALUES (?, ?)");
                $stmt->bind_param("sb", $fileName, $fileContent);
                $stmt->send_long_data(1, $fileContent);
                if ($stmt->execute()) {
                    echo '<script>alert("The file ' . basename($fileName) . ' has been uploaded."); window.location.href = "index.html";</script>';
                } else {
                    echo '<script>alert("Sorry, there was an error uploading your file."); window.location.href = "index.html";</script>';
                }
                $stmt->close();
                $conn->close();
            } else {
                echo '<script>alert("There was an error uploading your file."); window.location.href = "index.html";</script>';
            }
        }
?>
        