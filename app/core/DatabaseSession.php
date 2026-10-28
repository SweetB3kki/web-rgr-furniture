<?php
declare(strict_types=1);

class DatabaseSession implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
    private ?PDO $connection=null;
    private string $lockedId='';
    private bool $collectExpired=false;

    public function open(string $path,string $name):bool {
        $this->connection=DB::connect();
        return true;
    }
    public function close():bool {
        if ($this->connection?->inTransaction()) $this->connection->rollBack();
        if ($this->connection && $this->collectExpired) {
            $query=$this->connection->prepare('DELETE FROM web_sessions WHERE expires_at<? LIMIT 100');
            $query->execute([time()]);
        }
        $this->lockedId='';
        $this->connection=null;
        return true;
    }
    public function read(string $id):string|false {
        $query=$this->connection->prepare('INSERT IGNORE INTO web_sessions(id,payload,expires_at) VALUES (?, ?, ?)');
        $query->execute([$id,'',time()+$this->lifetime()]);
        // A separate connection locks this session without nesting application transactions.
        $this->connection->beginTransaction();
        $query=$this->connection->prepare('SELECT payload,expires_at FROM web_sessions WHERE id=? FOR UPDATE');
        $query->execute([$id]);
        $row=$query->fetch();
        $this->lockedId=$id;
        return $row && (int)$row['expires_at']>time() ? (string)$row['payload'] : '';
    }
    public function write(string $id,string $data):bool {
        if ($this->connection->inTransaction() && $this->lockedId!==$id) $this->connection->commit();
        $query=$this->connection->prepare('INSERT INTO web_sessions(id,payload,expires_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),expires_at=VALUES(expires_at)');
        $query->execute([$id,$data,time()+$this->lifetime()]);
        if ($this->connection->inTransaction()) $this->connection->commit();
        $this->lockedId='';
        return true;
    }
    public function destroy(string $id):bool {
        $query=$this->connection->prepare('DELETE FROM web_sessions WHERE id=?');
        $query->execute([$id]);
        if ($this->connection->inTransaction()) $this->connection->commit();
        $this->lockedId='';
        return true;
    }
    public function gc(int $max_lifetime):int|false {
        if ($this->connection->inTransaction()) { $this->collectExpired=true;return 0; }
        $query=$this->connection->prepare('DELETE FROM web_sessions WHERE expires_at<? LIMIT 100');
        $query->execute([time()]);
        return $query->rowCount();
    }
    public function validateId(string $id):bool {
        if (!preg_match('/^[a-zA-Z0-9,-]{16,128}$/D',$id)) return false;
        $query=$this->connection->prepare('SELECT id FROM web_sessions WHERE id=? AND expires_at>?');
        $query->execute([$id,time()]);
        return (bool)$query->fetch();
    }
    public function updateTimestamp(string $id,string $data):bool { return $this->write($id,$data); }
    private function lifetime():int { return max(1440,(int)ini_get('session.gc_maxlifetime')); }
}
