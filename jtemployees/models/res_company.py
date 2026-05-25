from odoo import models, fields, exceptions, api

class res_company(models.Model):
    _inherit = 'res.company'
    
    
    jt_salary_start_date = fields.Datetime(
        string="Salary Calculation Start Date",
        help="Defines the starting date used to calculate payroll, shortages, and related salary computations.",
        tracking=True
    )

    jt_absence_multiplying_factor = fields.Float(
        string="Absence Penalty Multiplier",
        default=3.0,
        help="Multiplier applied to calculated Absence amounts when computing salary.",
        tracking=True
    )
    
    jt_shortage_multiplying_factor = fields.Float(
        string="Shortage Penalty Multiplier",
        default=3.0,
        help="Multiplier applied to calculated shortage amounts when computing salary.",
        tracking=True
    )

    jt_overtime_multiplying_factor = fields.Float(
        string="Overtime Rate Multiplier",
        default=1.5,
        help="Multiplier applied to approved overtime hours when calculating overtime compensation.",
        tracking=True
    )

    jt_seniority_start_date = fields.Datetime(
        string="Seniority Calculation Start Date",
        help="The reference date from which employee seniority is calculated. Compared against the employee’s joining date.",
        tracking=True
    )

    jt_seniority_percentage_per_year = fields.Integer(
        string="Annual Seniority Increase (%)",
        help="Percentage increase applied to salary or benefits for each completed year of service.",
        tracking=True
    )

    jt_seniority_max_year = fields.Integer(
        string="Maximum Seniority Years",
        default=0,
        help="Maximum number of years considered for seniority calculations. Set to 0 for no limit.",
        tracking=True
    )

    jt_marital_amount = fields.Float(
        string="Marital Allowance Amount",
        default=0,
        help="Fixed allowance granted to married employees.",
        tracking=True
    )

    jt_child_amount = fields.Float(
        string="Child Allowance Amount",
        default=0,
        help="Allowance granted per eligible child.",
        tracking=True
    )

    jt_max_childs = fields.Integer(
        string="Maximum Eligible Children",
        default=0,
        help="Maximum number of children eligible for child allowance. Any additional children are not counted.",
        tracking=True
    )

    jt_monthly_leave_requests = fields.Integer(
        string="Monthly Leave Allocation (Days)",
        default=2,
        help="Number of paid leave days allocated per month for standard leave requests.",
        tracking=True
    )

    jt_max_monthly_leave_requests = fields.Integer(
        string="Maximum Monthly Leave Accumulation (Days)",
        default=24,
        help="Maximum number of leave days that can be accumulated for standard leave requests.",
        tracking=True
    )

    jt_monthly_timeoff_requests = fields.Integer(
        string="Monthly Time-Off Allocation (Hours)",
        default=4,
        help="Number of time-off hours allocated per month for short time-off requests.",
        tracking=True
    )

    jt_max_monthly_timeoff_requests = fields.Integer(
        string="Maximum Monthly Time-Off Accumulation (Hours)",
        default=4,
        help="Maximum number of time-off hours that can be accumulated.",
        tracking=True
    )

    jt_minimum_timeoff_request_minutes = fields.Integer(
        string="Minimum Time-Off Request Duration (Minutes)",
        default=30,
        help="Employees cannot submit time-off requests shorter than this duration.",
        tracking=True
    )

    jt_monthly_shortage_grace_hours = fields.Integer(
        string="Monthly Shortage Grace Hours",
        default=2,
        help="Number of hours per month automatically used to offset shortages. Applied internally and not shown to employees.",
        tracking=True
    )

    jt_shortage_flex_eligibility_threshold = fields.Integer(
        string="Shortage Flex Eligibility Threshold (Minutes)",
        default=30,
        help=(
            "Defines the maximum shortage duration (in minutes) that is eligible to be covered "
            "using the Monthly Shortage Grace Hours. Shortages exceeding this value will not "
            "consume from the flex hours."
        ),
        tracking=True
    )
    
    jt_shortage_minimum_warning = fields.Integer(
        string="Shortage minimum warnings (Minutes)",
        default=25,
        help="Number of minutes that deserves a warning that are consumed of the grace period, should be less than 'Shortage Flex Eligibility Threshold'",
        tracking=True
    )
    
    jt_general_hours_per_day = fields.Float(
        string="General Hours Per Day",
        default=8,
        help="The General Hours Per Day is used to calculate the pay per day for overtime calculations",
        tracking=True
    )
    
    jt_period_between_sessions = fields.Float(
        string="Period between two sessions",
        default=16,
        help="Used so that the finger print system can understand that a new check in is not check out",
        tracking=True
    )
    
    
    jt_insurance_percentage = fields.Float(
        string="Insurance Percentage",
        default=5,
        help="Used to calculate the insurance percentage in payrolls",
        tracking=True
    )

