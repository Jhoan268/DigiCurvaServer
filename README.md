# DigiCurvaServer


classDiagram
    direction LR

    class Usuario {
        +int usuario_id
        +string nombre
        +string correo
        +string contrasena_hash
        +string direccion
        +string telefono
        +string foto_perfil_url
        +decimal karma
        +timestamp fecha_registro
    }

    class Repartidor {
        +int repartidor_id
        +string nombre
        +string correo
        +string telefono
        +string foto_perfil_url
        +string ine_url
        +string nombre_empresa
        +string metodo_pago
        +string num_tarjeta_deposito
        +timestamp fecha_registro
    }

    class Producto {
        +int producto_id
        +int vendedor_id (FK)
        +string nombre
        +text descripcion
        +decimal precio
        +int cantidad_existencia
        +date fecha_publicacion
        +string imagen_url
    }

    class Oferta {
        +int oferta_id
        +int producto_id (FK, UNIQUE)
        +decimal precio_con_descuento
        +date fecha_publicacion
        +date fecha_finalizacion
    }

    class Pedido {
        +int pedido_id
        +int comprador_id (FK)
        +int repartidor_id (FK, Nullable)
        +timestamp fecha_pedido
        +enum estado
        +string direccion_envio
        +enum metodo_pago
        +string punto_encuentro_acordado (Nullable)
    }

    class Detalle_Pedido {
        +int detalle_id
        +int pedido_id (FK)
        +int producto_id (FK)
        +int cantidad
        +decimal precio_unitario_pagado
    }

    class Carrito_Compra {
        +int carrito_id
        +int usuario_id (FK)
        +int producto_id (FK)
        +int cantidad
        +timestamp fecha_agregado
    }

    class Anuncio {
        +int anuncio_id
        +int usuario_id (FK)
        +string titulo
        +text mensaje
        +date fecha_inicio
        +date fecha_fin
        +decimal costo
    }

    %% Relaciones (Cardinalidad)
    Usuario "1" --> "0..*" Producto : vende
    Usuario "1" --> "0..*" Pedido : compra
    Usuario "1" --> "0..*" Carrito_Compra : tiene
    Usuario "1" --> "0..*" Anuncio : publica

    Producto "1" --> "0..1" Oferta : tiene

    Pedido "1" --> "1..*" Detalle_Pedido : contiene
    Producto "1" --> "0..*" Detalle_Pedido : está en

    Pedido "0..*" --> "0..1" Repartidor : asignado a

    Usuario "1" --> "0..*" Carrito_Compra : tiene
    Producto "1" --> "0..*" Carrito_Compra : incluido en
