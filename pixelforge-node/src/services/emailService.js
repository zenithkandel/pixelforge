const nodemailer = require('nodemailer');
const config = require('../config');

let transporter = null;

function getTransporter() {
  if (!transporter) {
    transporter = nodemailer.createTransport({
      host: config.smtp.host,
      port: config.smtp.port,
      secure: config.smtp.port === 465,
      auth: {
        user: config.smtp.user,
        pass: config.smtp.pass
      }
    });
  }
  return transporter;
}

async function sendVerificationEmail(email, username, token) {
  const verifyUrl = `http://${process.env.HOST || 'localhost'}:${config.server.port}/api/auth/verify-email?token=${token}`;
  
  const html = `
    <!DOCTYPE html>
    <html>
    <head>
      <style>
        body { font-family: Arial, sans-serif; background: #0a0a0f; color: #e0e0e0; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #161622; border-radius: 12px; padding: 30px; border: 1px solid #2a2a3a; }
        h1 { color: #00ff88; margin-bottom: 20px; }
        p { line-height: 1.6; margin-bottom: 15px; }
        .button { display: inline-block; background: #00ff88; color: #0a0a0f; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 20px 0; }
        .button:hover { background: #00cc6a; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #2a2a3a; color: #888; font-size: 12px; }
      </style>
    </head>
    <body>
      <div class="container">
        <h1>Welcome to PixelForge, ${username}!</h1>
        <p>Thank you for registering. Please verify your email address by clicking the button below:</p>
        <a href="${verifyUrl}" class="button">Verify Email</a>
        <p>Or copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #00ff88;">${verifyUrl}</p>
        <p>This link will expire in 24 hours.</p>
        <div class="footer">
          <p>PixelForge - A Communal Pixel Canvas + Arcade Game Platform</p>
        </div>
      </div>
    </body>
    </html>
  `;

  try {
    const transport = getTransporter();
    await transport.sendMail({
      from: `"${config.smtp.fromName}" <${config.smtp.from}>`,
      to: email,
      subject: 'Verify your PixelForge account',
      html
    });
    console.log(`Verification email sent to ${email}`);
  } catch (err) {
    console.error('Failed to send verification email:', err.message);
  }
}

async function sendPasswordResetEmail(email, username, token) {
  const resetUrl = `http://${process.env.HOST || 'localhost'}:${config.server.port}/reset-password.html?token=${token}`;
  
  const html = `
    <!DOCTYPE html>
    <html>
    <head>
      <style>
        body { font-family: Arial, sans-serif; background: #0a0a0f; color: #e0e0e0; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #161622; border-radius: 12px; padding: 30px; border: 1px solid #2a2a3a; }
        h1 { color: #ff6b6b; margin-bottom: 20px; }
        p { line-height: 1.6; margin-bottom: 15px; }
        .button { display: inline-block; background: #ff6b6b; color: #0a0a0f; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 20px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #2a2a3a; color: #888; font-size: 12px; }
      </style>
    </head>
    <body>
      <div class="container">
        <h1>Password Reset - PixelForge</h1>
        <p>Hello ${username},</p>
        <p>You requested a password reset. Click the button below to set a new password:</p>
        <a href="${resetUrl}" class="button">Reset Password</a>
        <p>Or copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #ff6b6b;">${resetUrl}</p>
        <p>This link will expire in 1 hour. If you didn't request this, please ignore this email.</p>
        <div class="footer">
          <p>PixelForge - A Communal Pixel Canvas + Arcade Game Platform</p>
        </div>
      </div>
    </body>
    </html>
  `;

  try {
    const transport = getTransporter();
    await transport.sendMail({
      from: `"${config.smtp.fromName}" <${config.smtp.from}>`,
      to: email,
      subject: 'PixelForge Password Reset',
      html
    });
    console.log(`Password reset email sent to ${email}`);
  } catch (err) {
    console.error('Failed to send password reset email:', err.message);
  }
}

async function sendAchievementEmail(email, username, achievement) {
  const html = `
    <!DOCTYPE html>
    <html>
    <head>
      <style>
        body { font-family: Arial, sans-serif; background: #0a0a0f; color: #e0e0e0; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #161622; border-radius: 12px; padding: 30px; border: 1px solid #2a2a3a; text-align: center; }
        h1 { color: #ffd700; margin-bottom: 20px; }
        .achievement { background: #1a1a2a; border-radius: 12px; padding: 30px; margin: 20px 0; border: 2px solid #ffd700; }
        .icon { font-size: 48px; margin-bottom: 15px; }
        .name { color: #ffd700; font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .desc { color: #aaa; margin-bottom: 15px; }
        .reward { color: #00ff88; font-size: 18px; }
      </style>
    </head>
    <body>
      <div class="container">
        <h1>Achievement Unlocked!</h1>
        <div class="achievement">
          <div class="icon">${achievement.icon}</div>
          <div class="name">${achievement.name}</div>
          <div class="desc">${achievement.description}</div>
          <div class="reward">+${achievement.pxl_reward} PXL</div>
        </div>
        <p>Congratulations on earning this achievement, ${username}!</p>
        <div class="footer">
          <p>PixelForge - Keep playing and creating!</p>
        </div>
      </div>
    </body>
    </html>
  `;

  try {
    const transport = getTransporter();
    await transport.sendMail({
      from: `"${config.smtp.fromName}" <${config.smtp.from}>`,
      to: email,
      subject: `Achievement Unlocked: ${achievement.name}`,
      html
    });
  } catch (err) {
    console.error('Failed to send achievement email:', err.message);
  }
}

module.exports = {
  sendVerificationEmail,
  sendPasswordResetEmail,
  sendAchievementEmail
};