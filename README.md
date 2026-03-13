# ShopTest - Aplicación de Tienda en Línea

## Descripción del Proyecto

**ShopTest** es una aplicación full-stack de e-commerce desarrollada como prueba técnica de StrappBerry. Permite a los clientes navegar productos, agregar al carrito, realizar compras con pago mediante Mercado Pago, y consultar su historial de transacciones.

### Stack Tecnológico

**Frontend:**
- React 19 + TypeScript
- Vite 6.2
- Tailwind CSS 4
- React Query 5
- React Hook Form 7
- Axios 1.10

**Backend:**
- Laravel 12
- PHP 8.2
- JWT Authentication
- Pest 4 (Testing)
- MySQL

---

## Credenciales de Desarrollo

### Usuario Admin
```
Email: admin@mail.com
Password: 1234
Role: admin
```

### Usuario Cliente
```
Email: customer@mail.com
Password: 1234
Role: customer
```

---

## 💳 Tarjeta de Prueba Mercado Pago

Usa esta tarjeta para realizar pagos en desarrollo:

| Campo | Valor |
|-------|-------|
| **Número** | 5474 9254 3267 0366 |
| **Titular** | APRO |
| **Vencimiento** | 05/26 |
| **CVV** | 123 |

> ⚠️ En desarrollo, los pagos se simulan localmente. No requieren autenticación de pago en tiempo real.

---

## 🚀 Inicio Rápido

✅ Autenticación JWT
✅ Catálogo de productos con búsqueda y filtrado
✅ Carrito de compras persistente
✅ Checkout seguro con Mercado Pago
✅ Historial de compras (Mis Compras)
✅ Panel de transacciones con estado en tiempo real
✅ Gestión de productos (admin)
✅ Paginación y responsive design

---

## 🔗 Rutas Principales

### Cliente
- `/shop` - Catálogo de productos
- `/checkout` - Carrito y pago
- `/purchases` - Mis compras
- `/transactions` - Transacciones Mercado Pago

### Admin
- `/products` - Gestión de productos
- `/products/create` - Crear producto
- `/products/:id/edit` - Editar producto
