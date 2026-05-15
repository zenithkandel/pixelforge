async function creditPxl(userId, amount, type, description, relatedId = null) {
  return async (req, res, next) => {
    const pool = req.app.get('db');
    const conn = await pool.getConnection();
    
    try {
      await conn.beginTransaction();
      
      const [userRows] = await conn.execute(
        'SELECT pxl_balance FROM users WHERE id = ? FOR UPDATE',
        [userId]
      );
      
      if (userRows.length === 0) {
        throw new Error('User not found');
      }
      
      const currentBalance = userRows[0].pxl_balance;
      const newBalance = currentBalance + amount;
      
      if (newBalance < 0) {
        throw new Error('Insufficient balance');
      }
      
      await conn.execute(
        'UPDATE users SET pxl_balance = ?, total_pxl_earned = total_pxl_earned + ? WHERE id = ?',
        [newBalance, amount > 0 ? amount : 0, userId]
      );
      
      await conn.execute(
        'INSERT INTO pxl_transactions (user_id, amount, transaction_type, description, related_id) VALUES (?, ?, ?, ?, ?)',
        [userId, amount, type, description || null, relatedId]
      );
      
      await conn.commit();
      
      return newBalance;
    } catch (err) {
      await conn.rollback();
      throw err;
    } finally {
      conn.release();
    }
  };
}

async function creditPxlDirect(pool, userId, amount, type, description, relatedId = null) {
  const conn = await pool.getConnection();
  
  try {
    await conn.beginTransaction();
    
    const [userRows] = await conn.execute(
      'SELECT pxl_balance FROM users WHERE id = ? FOR UPDATE',
      [userId]
    );
    
    if (userRows.length === 0) {
      throw new Error('User not found');
    }
    
    const currentBalance = userRows[0].pxl_balance;
    const newBalance = currentBalance + amount;
    
    if (newBalance < 0) {
      throw new Error('Insufficient balance');
    }
    
    await conn.execute(
      'UPDATE users SET pxl_balance = ?, total_pxl_earned = total_pxl_earned + ? WHERE id = ?',
      [newBalance, amount > 0 ? amount : 0, userId]
    );
    
    await conn.execute(
      'INSERT INTO pxl_transactions (user_id, amount, transaction_type, description, related_id) VALUES (?, ?, ?, ?, ?)',
      [userId, amount, type, description || null, relatedId]
    );
    
    await conn.commit();
    
    return newBalance;
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

async function getBalance(pool, userId) {
  const [rows] = await pool.execute(
    'SELECT pxl_balance FROM users WHERE id = ?',
    [userId]
  );
  
  return rows.length > 0 ? rows[0].pxl_balance : 0;
}

async function getTransactionHistory(pool, userId, limit = 50, offset = 0) {
  const [transactions] = await pool.execute(
    `SELECT id, amount, transaction_type, description, created_at 
     FROM pxl_transactions 
     WHERE user_id = ? 
     ORDER BY created_at DESC 
     LIMIT ? OFFSET ?`,
    [userId, limit, offset]
  );
  
  return transactions;
}

module.exports = {
  creditPxl,
  creditPxlDirect,
  getBalance,
  getTransactionHistory
};