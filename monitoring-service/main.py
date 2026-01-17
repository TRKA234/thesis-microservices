from fastapi import FastAPI, HTTPException
from dotenv import load_dotenv
import os
import redis
import json
import pymysql
from motor.motor_asyncio import AsyncIOMotorClient
from datetime import datetime

# Load environment variables
load_dotenv()

app = FastAPI(title="Thesis Monitoring Service")

# --- Configuration ---
# Redis
REDIS_HOST = os.getenv("REDIS_HOST", "redis")
REDIS_PORT = int(os.getenv("REDIS_PORT", 6379))
redis_client = redis.Redis(host=REDIS_HOST, port=REDIS_PORT, decode_responses=True)

# MySQL (Submission DB)
MYSQL_CONFIG = {
    "host": os.getenv("MYSQL_HOST"),
    "port": int(os.getenv("MYSQL_PORT", 3306)),
    "user": os.getenv("MYSQL_USER"),
    "password": os.getenv("MYSQL_PASSWORD"),
    "database": os.getenv("MYSQL_DB"),
    "cursorclass": pymysql.cursors.DictCursor
}

# MongoDB (Guidance DB)
MONGO_URI = os.getenv("MONGODB_URI")
mongo_client = AsyncIOMotorClient(MONGO_URI)
mongo_db = mongo_client.get_default_database()

# --- Health Check ---
@app.get("/health")
async def health_check():
    return {
        "status": "healthy",
        "service": "monitoring-service",
        "timestamp": datetime.now().isoformat()
    }

# --- Statistics Logic ---
@app.get("/stats/global")
async def get_global_stats():
    cache_key = "global_stats_dashboard"
    
    # 1. Coba ambil dari Cache Redis
    try:
        cached_data = redis_client.get(cache_key)
        if cached_data:
            stats = json.loads(cached_data)
            stats["source"] = "cache"
            return stats
    except Exception as e:
        print(f"Redis Cache Error: {e}")

    # 2. Jika tidak ada di cache, hitung dari Database
    try:
        # --- A. Data dari MySQL (Submissions & Milestones) ---
        conn = pymysql.connect(**MYSQL_CONFIG)
        try:
            with conn.cursor() as cursor:
                # Total & Kelulusan
                cursor.execute("""
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'lulus' THEN 1 ELSE 0 END) as graduated
                    FROM submissions
                """)
                mysql_basic = cursor.fetchone()

                # Rata-rata Progress Berdasarkan Milestone (ACC)
                cursor.execute("""
                    SELECT 
                        AVG(sub_progress) as avg_progress
                    FROM (
                        SELECT 
                            s.id,
                            (COUNT(CASE WHEN m.status = 'acc' THEN 1 END) * 100.0 / NULLIF(COUNT(m.id), 0)) as sub_progress
                        FROM submissions s
                        LEFT JOIN milestones m ON s.id = m.submission_id
                        GROUP BY s.id
                    ) AS progress_table
                """)
                mysql_progress = cursor.fetchone()
        finally:
            conn.close()

        # --- B. Data dari MongoDB (Guidance Messages) ---
        total_messages = await mongo_db.messages.count_documents({})

        # --- C. Gabungkan Hasil ---
        stats = {
            "total_submissions": mysql_basic['total'] or 0,
            "graduated_submissions": int(mysql_basic['graduated'] or 0),
            "average_progress_percentage": round(float(mysql_progress['avg_progress'] or 0), 2),
            "total_guidance_messages": total_messages,
            "last_updated": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        }

        # 3. Simpan ke Redis (Expire dalam 60 detik)
        try:
            redis_client.setex(cache_key, 60, json.dumps(stats))
        except:
            pass

        stats["source"] = "database_live"
        return stats

    except pymysql.MySQLError as e:
        raise HTTPException(status_code=500, detail=f"MySQL Error: {str(e)}")
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Internal Error: {str(e)}")

# --- Detailed Stats (Optional) ---
@app.get("/stats/submissions-status")
async def get_status_breakdown():
    """Melihat distribusi status pengajuan (pengajuan, disetujui, lulus, ditolak)"""
    try:
        conn = pymysql.connect(**MYSQL_CONFIG)
        with conn.cursor() as cursor:
            cursor.execute("SELECT status, COUNT(*) as count FROM submissions GROUP BY status")
            result = cursor.fetchall()
        conn.close()
        return {"success": True, "data": result}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    import uvicorn
    # Menjalankan server pada port 8084
    uvicorn.run(app, host="0.0.0.0", port=8084)