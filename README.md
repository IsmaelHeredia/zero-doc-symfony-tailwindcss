# ZeroDoc

**ZeroDoc** es una plataforma para compartir texto de forma privada basada en el modelo *zero-knowledge*: el contenido se cifra en el navegador antes de enviarse y el servidor solo almacena datos cifrados, sin conocer nunca la clave. Está diseñada con arquitectura DDD y se ejecuta localmente con Docker.

## Funcionalidades principales

### Pastes cifrados

- **Cifrado en el cliente:** AES-GCM de 256 bits con la Web Crypto API, antes de enviar nada al servidor.

- **Clave en el enlace:** la clave viaja en el fragmento de la URL (`/p/{id}#{clave}`), que el navegador no envía. Quien tenga el enlace completo puede leer el paste.

- **Contraseña opcional:** segundo factor derivado con PBKDF2-SHA256 (600 000 iteraciones o más) a partir de la contraseña y un secreto del enlace.

- **Reglas de vida:** caducidad, límite de lecturas, *burn after reading* y límite de tamaño.

- **Borrado real:** destrucción física del contenido, con token de borrado para pastes anónimos.

### Cuentas, roles y permisos

- **Registro e inicio de sesión:** con protección frente a fuerza bruta y sesiones seguras.

- **Mis pastes:** listado de metadatos desde el servidor; los enlaces completos se reconstruyen con las claves guardadas en tu navegador, con opción de olvidarlas en un clic.

- **Control de acceso por permisos (RBAC):** roles `member`, `moderator` y `admin` con permisos sobre acciones de la plataforma. Ningún permiso da acceso al contenido ni a los enlaces.

### Organización y moderación

- **Tags:** etiquetas propias para organizar tus pastes (tabla pivote), con nombres únicos por usuario.

- **Reportes de abuso:** cualquiera puede reportar un paste, con o sin cuenta; solo viaja el ID, nunca la clave. Los moderadores descartan el reporte o destruyen el paste por ID sin leerlo.

- **Administración:** gestión de roles de usuarios y de permisos de roles desde la propia interfaz.

- **Auditoría:** registro de borrados y de cambios de roles y permisos, sin contenido ni IPs.

### Interfaz

- **Tema claro y oscuro:** con botón sol/luna, recordado por navegador y colores personalizables desde un solo fichero.

- **Ayuda y Acerca de:** ventanas integradas con la guía de uso.

## Seguridad

- CSP estricta, sin scripts ni estilos en línea y sin recursos externos.
- SRI en todos los scripts y hojas de estilo, con un único paquete por pantalla.
- Cabeceras de seguridad en todas las respuestas.
- **Límites conocidos:** el servidor entrega el JavaScript, los metadatos y los tags van en claro y los ve el administrador, que nunca puede leer contenido ni reconstruir enlaces.

## Capturas de pantalla

A continuación, se muestran algunas imágenes de la aplicación en funcionamiento:

![screenshot](https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEjNjB8e0p2ZItLejVO7KiWqKPgDHZdfgItZpCfP_JoD7SP6dixCQ4jMh3_47GcKRidnYpZuXnih33L7cPz6pGu8NtFJoISnHlMoJoAwLHvUATFAv8YEl3efU-BDiVUuyYqSuOjotd3jCI2yEyDbwrfTNZ7v0EXW8rMJ4zTQ7Ng_Aun618tHCEbTp89VcnQ/s1499/1.png)

![screenshot](https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEj8CiXoeYufxGZ83HY5C5i_pNYEyP-xOPEhdbtWIY3Fv8ZGs1VKtB4paryo5ithBYO0MC2pfOZdoyPJiaD0g3tfPhYCYUddYR-hWpKPaYVj8-wTWWRZYom93vdgfeC24xWUKKeFKnyy5ADY3hsFjreu5PzoxheLRDLi54ebjVJZebNHDy365YrMZD0giLI/s1523/2.png)

![screenshot](https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEjOQOQmrF7-kMA-iNwkgAtezbhrEhkRl4GFBhtv2w5R3W1Q4SWT4wuhdwbWXmDNMN6ePhoDdu4TAusnR6TwyY-c7aR4MtgaAN9so3W2nhqjShyphenhyphen_da8ixHrxi82ZUWW3SNNEuRLvf_RjGnQcNCHF0IZutjgZjc8QyblINkzQKoXp6Xy6MEwH74bWlz6MIko/s1476/3.png)

![screenshot](https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEgnbhc2hN4c8zSGIV6eslgaravxC6zVlV5QqM9te0TU-ZxAyyyNqZrTMcnEaABoEzecvTA3ka-qOOlwNYU5VvOkFrdUEoeE96jNmXHub8ZgGNX-iK8_51Xzk_RjCCb8g3QH4VCaG0wbgq0DOW6OoV3K9Yev58S6Nly4IgITq8gU27e-maUPOq7QYTWgBzE/s1461/4.png)

![screenshot](https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEj9uTAm-Nm7Pji3ItJkUGD0IV47GFX4z3JSvfvFAQoMLgnvY_tBZ0-5PkBuK2nxgjRkYx8xNI163kS2mCBgUsuIuPzRX3bU0eyDM8mn8UjAPk_sWVEZkhHOrMf08IdF2kRlCi7IEkVC7ZhXUaic0sVM5RjLQMirsTWZlYwIvncFA9aO3JXQx_cFL9YQDKE/s1465/5.png)

### Instalación del proyecto

Requisitos: **Docker** con Compose v2 y **bash** (Linux, macOS o WSL2). No hace falta tener PHP, Composer ni Node instalados.

**1.** Clonar el repositorio

```
git clone https://github.com/IsmaelHeredia/zero-doc-symfony-tailwindcss.git
cd zero-doc-symfony-tailwindcss
```

**2.** Instalación completa (contenedores, dependencias, base de datos, roles y assets)

```
bash bin/dev setup
```

**3.** Crear el primer administrador

```
bash bin/dev admin admin@localhost.com
```

**4.** Abrir la aplicación

```
http://localhost:8000
```

Usa siempre `localhost` o `127.0.0.1`: la criptografía del navegador solo funciona en un contexto seguro.

### Uso diario

```
bash bin/dev up       # iniciar
bash bin/dev down     # detener (los datos se conservan)
bash bin/dev assets   # recompilar CSS/JS tras editarlos
bash bin/dev logs     # ver logs
```

### Roles y permisos

| Rol | Permisos |
|---|---|
| `member` | crear pastes (permanentes y grandes), listar los propios, gestionar tags |
| `moderator` | destruir pastes por ID, revisar reportes |
| `admin` | todo lo anterior, más leer la auditoría y gestionar usuarios y roles |

Los roles son aditivos. Para promover a un usuario:

```
bash bin/dev console app:identity:assign-role usuario@localhost.com moderator
```

### Personalización del diseño

Todos los colores están en `assets-src/theme.css`, como variables para el tema claro y el oscuro. Tras editar, ejecuta:

```
bash bin/dev assets
```

### Pruebas

El proyecto incluye pruebas de dominio, aplicación, infraestructura (MySQL real) y HTTP, además de pruebas de la criptografía y del almacén de claves en JavaScript.

```
bash bin/dev test
```

Para una comprobación completa (hashes SRI, esquema de base de datos y todas las pruebas):

```
bash bin/dev check
```