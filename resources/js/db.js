import Dexie from "dexie";

const db = new Dexie("POS_DB");

db.version(1).stores({
    products: '++id, name, price, stock',
    cart: '++id, product_id, quantity',
    sales: '++id, cart_items, total'
});

export default db;