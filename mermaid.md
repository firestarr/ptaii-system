```mermaid
flowchart TD
    A[Start Accounting System] --> B[Initial Setup]
    
    %% Initial Setup Phase
    B --> B1[Create Chart of Accounts]
    B --> B2[Setup Accounting Periods]
    B --> B3[Setup Bank Accounts]
    B --> B4[Configure Exchange Rates]
    
    B1 --> C[Master Data Setup]
    B2 --> C
    B3 --> C
    B4 --> C
    
    %% Master Data Phase
    C --> C1[Register Fixed Assets]
    C --> C2[Setup Budget Plans]
    C --> C3[Configure Tax Codes]
    
    C1 --> D[Daily Operations]
    C2 --> D
    C3 --> D
    
    %% Daily Operations
    D --> D1[Record Journal Entries]
    D --> D2[Create Customer Receivables]
    D --> D3[Create Vendor Payables]
    D --> D4[Process Tax Transactions]
    
    %% Payment Processing
    D1 --> E[Payment Processing]
    D2 --> E1[Receive Customer Payments]
    D3 --> E2[Make Vendor Payments]
    
    E1 --> F[Bank Management]
    E2 --> F
    
    %% Bank Management
    F --> F1[Bank Reconciliation]
    F1 --> F2{Reconciliation Balanced?}
    F2 -->|No| F3[Investigate Differences]
    F3 --> F1
    F2 -->|Yes| F4[Finalize Reconciliation]
    
    %% Asset Management
    D --> G[Asset Management]
    G --> G1[Calculate Depreciation]
    G1 --> G2[Create Depreciation Entries]
    G2 --> G3[Update Asset Values]
    
    %% Budget Management
    D --> H[Budget Management]
    H --> H1[Update Actual Amounts]
    H1 --> H2[Calculate Variances]
    H2 --> H3[Generate Budget Reports]
    
    %% Period End Process
    F4 --> I[Period End Process]
    G3 --> I
    H3 --> I
    
    I --> I1[Review All Transactions]
    I1 --> I2[Generate Trial Balance]
    I2 --> I3{Trial Balance OK?}
    I3 -->|No| I4[Make Adjusting Entries]
    I4 --> I2
    I3 -->|Yes| I5[Close Accounting Period]
    
    %% Financial Reporting
    I5 --> J[Financial Reporting]
    J --> J1[Income Statement]
    J --> J2[Balance Sheet]
    J --> J3[Cash Flow Statement]
    J --> J4[Accounts Receivable Report]
    J --> J5[Accounts Payable Report]
    J --> J6[Tax Summary Report]
    J --> J7[Budget Variance Report]
    
    %% New Period
    J1 --> K[Start New Period]
    J2 --> K
    J3 --> K
    J4 --> K
    J5 --> K
    J6 --> K
    J7 --> K
    
    K --> D
    
    %% Styling
    classDef setupPhase fill:#e1f5fe
    classDef masterData fill:#f3e5f5
    classDef operations fill:#e8f5e8
    classDef payments fill:#fff3e0
    classDef reports fill:#fce4ec
    classDef decisions fill:#fff8e1
    
    class B,B1,B2,B3,B4 setupPhase
    class C,C1,C2,C3 masterData
    class D,D1,D2,D3,D4,E,E1,E2 operations
    class F,F1,F3,F4,G,G1,G2,G3,H,H1,H2,H3 payments
    class I,I1,I2,I4,I5,J,J1,J2,J3,J4,J5,J6,J7,K reports
    class F2,I3 decisions

    ```