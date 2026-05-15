const mysql = require('mysql2/promise');
const config = require('./config');

let pool = null;

function createPool() {
  if (!pool) {
    pool = mysql.createPool({
      host: config.db.host,
      port: config.db.port,
      user: config.db.user,
      password: config.db.password,
      database: config.db.database,
      waitForConnections: config.db.waitForConnections,
      connectionLimit: config.db.connectionLimit,
      multipleStatements: config.db.multipleStatements,
      namedPlaceholders: config.db.namedPlaceholders
    });
  }
  return pool;
}

function getPool() {
  if (!pool) {
    return createPool();
  }
  return pool;
}

async function testConnection() {
  try {
    const p = getPool();
    const connection = await p.getConnection();
    await connection.ping();
    connection.release();
    return true;
  } catch (error) {
    console.error('Database connection failed:', error.message);
    return false;
  }
}

async function closePool() {
  if (pool) {
    await pool.end();
    pool = null;
  }
}

module.exports = {
  createPool,
  getPool,
  testConnection,
  closePool
};