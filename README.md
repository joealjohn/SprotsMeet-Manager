# 🏆 SportsMeet Manager

## 🎯 Overview

**SportsMeet Manager** is a powerful and user-friendly platform for organizing, managing, and participating in sports events. Whether you're an athlete, coach, or administrator, SportsMeet offers everything you need to create, browse, join, and track your sports activities with ease.

---

## 🚀 Features

- 👤 **User Registration & Authentication**
- 🗓️ **Browse, Join & Manage Sports Events**
- 🛡️ **Admin Dashboard for Event Management**
- 🔔 **Real-Time Notifications & Recent Activity Tracking**
- 🌏 **Timezone-Aware Scheduling (IST/UTC)**
- 📊 **User Statistics & Analytics**
- 📱 **Responsive & Mobile-Friendly Design**
- 🎨 **Customizable UI with Modern Theming**
- 🔍 **Advanced Search & Filtering**
- 💾 **Auto-save Forms & Data Recovery**
- 📈 **Future-proof: Chart.js, PWA, Service Worker Ready**

---

## 🛠️ Tech Stack

- **Backend:** PHP 7.4+, MySQL
- **Frontend:** Bootstrap 5, FontAwesome 6, Poppins Font
- **Other:** Service Worker (PWA), Chart.js (future), LocalStorage

---

## 🏁 Getting Started

### 1. 📥 Clone the Repository

```bash
git clone https://github.com/yourusername/sportsmeet-manager.git
cd sportsmeet-manager
```

### 2. 🗄️ Set Up the Database

- Import the provided `database.sql` file into your MySQL server.
- Copy `config/database.sample.php` to `config/database.php` and fill in your database credentials.

### 3. ⚙️ Configure the Application

- Verify your database connection in `config/database.php`.
- Adjust settings in `config/app.php` or `.env` if present.

### 4. 📂 Set Up Assets

- Place your logo and images in `assets/images/`.
- Put your custom styles in `assets/css/style.css`.

### 5. 🏃‍♂️ Run Locally

- Deploy on a local web server (Apache, Nginx, XAMPP, etc.).
- Access via `http://localhost/sportsmeet-manager` or your configured local domain.

### 6. ✍️ First Steps

- Register a new user.
- Browse events as a user.
- Log in as admin to create/manage events.
- Join events and track your activity!

---

## ⚡ Usage Guide

- **Register/Login:** Create an account and log in.
- **Browse Events:** Use filters to find sports events by name, date, sport, or venue.
- **Join Events:** Click "Join Event" on available events. Confirm your registration.
- **View Dashboard:** See upcoming, completed, and total events, plus recent activity.
- **Admin Panel:** (Admins only) Create, edit, or delete events. View user stats.
- **Notifications:** Get alerts for new events, changes, and reminders.
- **Auto-Save:** Never lose in-progress forms—auto-save keeps your work safe!
- **Mobile Friendly:** Use on any device, from desktop to mobile.

---

## 🏗️ Project Structure

```
sportsmeet-manager/
│
├── assets/
│   ├── css/
│   ├── images/
│   └── js/
├── config/
├── includes/
├── admin/
├── user/
├── auth/
├── index.php
├── README.md
└── ...
```

---

## 👨‍💻 Contributing

We love contributions! 🚀

1. Fork the repository
2. Create a new branch (`git checkout -b feature/your-feature`)
3. Commit your changes
4. Push to your fork (`git push origin feature/your-feature`)
5. Create a Pull Request

---

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgements

- [Bootstrap](https://getbootstrap.com/)
- [FontAwesome](https://fontawesome.com/)
- [Poppins Font](https://fonts.google.com/specimen/Poppins)
- [Chart.js](https://www.chartjs.org/)
---

- **Discussions:** Start or join a discussion on [GitHub Discussions](https://github.com/yourusername/sportsmeet-manager/discussions).
- **Email:** support@sportsmeetmanager.com

---

_Developed as a college Mini Project!_
