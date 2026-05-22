<?php
// Reemplaza con los datos de tu servidor de correo
$to_email = "info@piaggiogroup.com.ar";
$subject = "Nuevo mensaje desde el formulario de contacto";
$from_email = $_POST['email']; // El remitente será el email ingresado por el usuario
$message = $_POST['message'];
$nombre = $_POST['nombre'];

// Encabezados del correo electrónico
$headers = "From: $from_email";

// Envía el correo electrónico
if (mail($to_email, $subject, $message, $headers)) {
    // Éxito al enviar el correo
    echo '<script>alert("Tu mensaje ha sido enviado correctamente."); window.location.href = "index.html";</script>';
} else {
    // Error al enviar el correo
    echo '<script>alert("Hubo un error al enviar tu mensaje. Por favor, intenta nuevamente más tarde."); window.location.href = "index.html";</script>';
}
?>