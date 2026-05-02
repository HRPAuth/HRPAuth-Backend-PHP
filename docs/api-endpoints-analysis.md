# HRPAuth 后端 API 端点分析报告

## 1. 登录 API

### 请求入口
- **URL**: `/login`
- **请求方法**: POST
- **路由处理**: `controllers/AuthController@login`

### 请求值类型
- **Content-Type**: `application/json`

### 请求参数
| 参数名 | 类型 | 必须 | 描述 |
|--------|------|------|------|
| email | string | 是 | 用户邮箱地址 |
| password | string | 是 | 用户密码 |

### 文件系统与数据库操作
- **数据库操作**:
  - 查询用户信息：`SELECT uid, password FROM users WHERE email = ? LIMIT 1`
  - 更新用户 token：`UPDATE users SET remember_token = ? WHERE uid = ?`

### 处理操作
1. 验证请求方法是否为 POST
2. 解析 JSON 请求数据
3. 验证邮箱格式
4. 查询用户信息
5. 验证密码
6. 生成随机 token
7. 更新用户 token 到数据库
8. 返回登录结果

### 返回值类型
- **Content-Type**: `application/json`

### 期望的返回值用途
| 状态码 | 成功响应 | 失败响应 | 用途 |
|--------|----------|----------|------|
| 200 | `{"success": true, "message": "Login successful", "token": "string", "uid": "number"}` | - | 登录成功，返回用户 token 和 uid |
| 400 | - | `{"success": false, "message": "Invalid email"}` | 邮箱格式错误 |
| 401 | - | `{"success": false, "message": "Email or password incorrect"}` | 邮箱或密码错误 |
| 405 | - | `{"success": false, "message": "Method Not Allowed"}` | 请求方法错误 |

## 2. 注册 API

### 请求入口
- **URL**: `/register`
- **请求方法**: POST
- **路由处理**: `controllers/AuthController@register`

### 请求值类型
- **Content-Type**: `application/json`

### 请求参数
| 参数名 | 类型 | 必须 | 描述 |
|--------|------|------|------|
| email | string | 是 | 用户邮箱地址 |
| username | string | 是 | 用户名 |
| password | string | 是 | 用户密码 |
| password2 | string | 是 | 确认密码 |

### 文件系统与数据库操作
- **数据库操作**:
  - 检查邮箱是否存在：`SELECT uid FROM users WHERE email = ? LIMIT 1`
  - 创建用户：`INSERT INTO users (email, username, realname, nickname, score, password, ip, last_sign_at, register_at, verified, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`

### 处理操作
1. 验证请求方法是否为 POST
2. 解析 JSON 请求数据
3. 验证邮箱格式
4. 验证用户名长度
5. 验证密码长度
6. 验证两次密码是否一致
7. 连接数据库
8. 检查邮箱是否已注册
9. 密码哈希处理
10. 生成验证 token
11. 插入用户数据
12. 返回注册结果

### 返回值类型
- **Content-Type**: `application/json`

### 期望的返回值用途
| 状态码 | 成功响应 | 失败响应 | 用途 |
|--------|----------|----------|------|
| 200 | `{"success": true, "uid": "number", "message": "Register successful"}` | - | 注册成功，返回用户 uid |
| 400 | - | `{"success": false, "message": "Invalid email"}` | 邮箱格式错误 |
| 400 | - | `{"success": false, "message": "Nickname too short"}` | 昵称长度不足 |
| 400 | - | `{"success": false, "message": "Password too short"}` | 密码长度不足 |
| 400 | - | `{"success": false, "message": "Passwords not match"}` | 两次密码不一致 |
| 409 | - | `{"success": false, "message": "Email already registered"}` | 邮箱已注册 |
| 405 | - | `{"success": false, "message": "Method Not Allowed"}` | 请求方法错误 |
| 500 | - | `{"success": false, "message": "Database error"}` | 数据库错误 |

## 3. 邮件验证 API

### 请求入口
- **URL**: `/email-verification`
- **请求方法**: POST
- **路由处理**: `controllers/EmailVerificationController@handle`

### 请求值类型
- **Content-Type**: `application/json`

### 请求参数
根据不同的 action 参数，请求参数有所不同：

#### 3.1 发送测试邮件 (`action=send-test-email`)
| 参数名 | 类型 | 必须 | 描述 |
|--------|------|------|------|
| action | string | 是 | 固定值 "send-test-email" |
| to | string | 是 | 收件人邮箱地址 |
| subject | string | 是 | 邮件主题 |
| message | string | 是 | 邮件内容 |

#### 3.2 发送验证码 (`action=send-verification-code`)
| 参数名 | 类型 | 必须 | 描述 |
|--------|------|------|------|
| action | string | 是 | 固定值 "send-verification-code" |
| email | string | 是 | 用户邮箱地址 |

#### 3.3 验证验证码 (`action=verify-code`)
| 参数名 | 类型 | 必须 | 描述 |
|--------|------|------|------|
| action | string | 是 | 固定值 "verify-code" |
| email | string | 是 | 用户邮箱地址 |
| code | string | 是 | 验证码 |

### 文件系统与数据库操作
- **文件系统操作**:
  - 发送邮件：使用 fsockopen 连接 SMTP 服务器
- **数据库操作**:
  - 更新用户验证状态：`UPDATE users SET verified = 1 WHERE email = ?`
- **缓存操作**:
  - 存储验证码：`storeVerificationCode($email, $code)`
  - 获取验证码：`getVerificationCode($email)`
  - 删除验证码：`deleteVerificationCode($email)`

### 处理操作
1. 验证请求方法是否为 POST
2. 解析 JSON 请求数据
3. 根据 action 参数执行不同操作：
   - **send-test-email**: 验证参数，发送测试邮件
   - **send-verification-code**: 验证邮箱，检查是否已发送验证码，生成验证码，存储验证码，发送邮件
   - **verify-code**: 验证邮箱和验证码，删除验证码，更新用户验证状态
4. 返回操作结果

### 返回值类型
- **Content-Type**: `application/json`

### 期望的返回值用途
| 状态码 | 成功响应 | 失败响应 | 用途 |
|--------|----------|----------|------|
| 200 | `{"success": true, "message": "邮件发送成功", "data": {"to": "string", "subject": "string"}}` | - | 测试邮件发送成功 |
| 200 | `{"success": true, "message": "Verification code sent successfully"}` | - | 验证码发送成功 |
| 200 | `{"success": true, "message": "Verification successful"}` | - | 验证码验证成功 |
| 400 | - | `{"success": false, "message": "收件人邮箱不能为空"}` | 测试邮件参数错误 |
| 400 | - | `{"success": false, "message": "Invalid email"}` | 邮箱格式错误 |
| 400 | - | `{"success": false, "message": "Verification code is required"}` | 验证码为空 |
| 400 | - | `{"success": false, "message": "Verification code expired or not found"}` | 验证码过期或不存在 |
| 400 | - | `{"success": false, "message": "Invalid verification code"}` | 验证码错误 |
| 400 | - | `{"success": false, "message": "Invalid action"}` | 无效的 action |
| 404 | - | `{"success": false, "message": "User not found or already verified"}` | 用户不存在或已验证 |
| 405 | - | `{"success": false, "message": "Method Not Allowed"}` | 请求方法错误 |
| 429 | - | `{"success": false, "message": "Verification code already sent, please wait"}` | 验证码已发送，请等待 |
| 500 | - | `{"success": false, "message": "Failed to store verification code"}` | 存储验证码失败 |
| 500 | - | `{"success": false, "message": "Failed to update verification status"}` | 更新验证状态失败 |
| 500 | - | `{"success": false, "message": "错误信息"}` | 邮件发送失败 |

## 4. 获取用户信息 API

### 请求入口
- **URL**: `/user`
- **请求方法**: POST
- **路由处理**: `controllers/UserController@getUser`

### 请求值类型
- **Content-Type**: `application/json`

### 请求参数
| 参数名 | 类型 | 必须 | 描述 |
|--------|------|------|------|
| remember_token | string | 是 | 用户登录 token |
| uid | string | 否 | 用户唯一标识符 |
| email | string | 否 | 用户邮箱地址 |

### 文件系统与数据库操作
- **数据库操作**:
  - 查询用户信息：`SELECT uid, email, username, avatar, verified FROM users WHERE remember_token = ? [AND uid = ?] [AND email = ?]`
  - 回退查询（无 avatar 字段）：`SELECT uid, email, username, verified FROM users WHERE remember_token = ? [AND uid = ?] [AND email = ?]`

### 处理操作
1. 启动会话
2. 连接数据库
3. 从不同来源获取参数：
   - POST 请求的 JSON 数据
   - POST 请求的表单数据
   - URL 参数
4. 验证 token 是否为空
5. 构建查询条件，根据提供的参数添加 uid 和 email 比对
6. 尝试查询用户信息（带 avatar 字段）
7. 如果失败，尝试回退查询（无 avatar 字段）
8. 验证用户是否存在
9. 构建用户数据
10. 返回用户信息

### 返回值类型
- **Content-Type**: `application/json`

### 期望的返回值用途
| 状态码 | 成功响应 | 失败响应 | 用途 |
|--------|----------|----------|------|
| 200 | `{"success": true, "message": "获取用户信息成功", "data": {"uid": "number", "email": "string", "username": "string", "avatar": "string|null", "verified": "boolean"}}` | - | 获取用户信息成功 |
| 200 | - | `{"success": false, "message": "未登录或登录已过期"}` | token 为空 |
| 200 | - | `{"success": false, "message": "用户不存在或token无效"}` | 用户不存在或 token 无效 |
| 200 | - | `{"success": false, "message": "服务器错误"}` | 数据库错误 |

## 5. 登出 API

### 请求入口
- **URL**: `/logout`
- **请求方法**: GET
- **路由处理**: `controllers/AuthController@logout`

### 请求值类型
- **Content-Type**: 无特定要求

### 请求参数
| 参数名 | 类型 | 必须 | 描述 |
|--------|------|------|------|
| remember_token | string | 否 | 用户登录 token |

### 文件系统与数据库操作
- **数据库操作**:
  - 清除用户 token：`UPDATE users SET remember_token = NULL WHERE remember_token = ?`
- **会话操作**:
  - 清除会话数据：`$_SESSION = []`
  - 销毁会话：`session_destroy()`

### 处理操作
1. 启动会话
2. 从不同来源获取 token：
   - POST 请求的 JSON 数据
   - POST 请求的表单数据
   - URL 参数
3. 如果 token 存在，清除数据库中的 token
4. 清除会话数据
5. 销毁会话
6. 重定向到登录页面

### 返回值类型
- **Content-Type**: 无特定返回值，执行重定向

### 期望的返回值用途
- 执行登出操作并重定向到登录页面

## 6. TOTP 生成 API

### 请求入口
- **URL**: `/totpgen`
- **请求方法**: GET
- **路由处理**: `controllers/TOTPController@generate`

### 请求值类型
- **Content-Type**: 无特定要求

### 请求参数
| 参数名 | 类型 | 必须 | 描述 |
|--------|------|------|------|
| secret | string | 是 | TOTP 密钥 |

### 文件系统与数据库操作
- **无**

### 处理操作
1. 验证 secret 参数是否存在
2. 生成 TOTP 验证码
3. 返回验证码

### 返回值类型
- **Content-Type**: `text/plain`

### 期望的返回值用途
| 状态码 | 成功响应 | 失败响应 | 用途 |
|--------|----------|----------|------|
| 200 | `字符串类型的 6 位数字验证码` | - | 生成 TOTP 验证码 |
| 400 | - | `Missing secret` | secret 参数缺失 |

## 总结

本项目提供了以下 API 端点：

1. **登录 API** (`/login`) - 用于用户登录，返回 token 和 uid
2. **注册 API** (`/register`) - 用于用户注册，返回 uid
3. **邮件验证 API** (`/email-verification`) - 用于发送测试邮件、发送验证码和验证验证码
4. **获取用户信息 API** (`/user`) - 用于获取用户信息
5. **登出 API** (`/logout`) - 用于用户登出
6. **TOTP 生成 API** (`/totpgen`) - 用于生成 TOTP 验证码

所有 API 端点都遵循 RESTful 设计原则，使用 JSON 格式返回数据（除了 TOTP 生成 API 返回纯文本）。数据库操作主要涉及用户表的查询和更新，文件系统操作主要是发送邮件。

这些 API 端点共同构成了一个完整的用户认证系统，支持用户注册、登录、邮箱验证、获取用户信息和登出等功能，同时提供了 TOTP 验证码生成功能以增强安全性。

### 路由结构

项目使用前端控制器模式，通过 `public/index.php` 统一处理所有请求，并根据路由配置将请求转发到相应的控制器方法：

| 路由 | 方法 | 控制器 | 方法 |
|------|------|--------|------|
| `/login` | POST | `AuthController` | `login` |
| `/register` | POST | `AuthController` | `register` |
| `/logout` | GET | `AuthController` | `logout` |
| `/user` | POST | `UserController` | `getUser` |
| `/email-verification` | POST | `EmailVerificationController` | `handle` |
| `/totpgen` | GET | `TOTPController` | `generate` |

这种路由结构使得 API 端点更加清晰和规范，便于维护和扩展。