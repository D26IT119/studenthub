# StudentHub Practical 8 ER Model

```mermaid
erDiagram
    COURSES ||--o{ STUDENTS : has
    STUDENTS ||--o{ REGISTRATIONS : makes
    EVENTS ||--o{ REGISTRATIONS : receives

    COURSES {
        int course_id PK
        varchar course_name UK
    }
    STUDENTS {
        int id PK
        varchar name
        varchar email UK
        varchar mobile
        int course_id FK
        tinyint year
        enum gender
        varchar password_hash
        timestamp created_at
    }
    EVENTS {
        int event_id PK
        varchar title
        date event_date
        varchar location
        varchar category
    }
    REGISTRATIONS {
        int reg_id PK
        int student_id FK
        int event_id FK
        timestamp registered_on
    }
```

One course can have many students. Students and events have a many-to-many
relationship, represented by `registrations`. The unique pair
(`student_id`, `event_id`) prevents duplicate sign-ups. Course names are stored
once in `courses`; the student table stores only the course key.

The SQL file creates the lab database as `studenthub_practical8` so it can be
imported alongside an existing StudentHub database without overwriting it.
