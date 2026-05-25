from odoo import models, fields, exceptions
from datetime import datetime, timedelta
from pytz import timezone
from odoo.tools import config

class payrolls_slips(models.Model):
    _name = 'jtemployees.payrolls.slips'
    _description = 'Payroll Slip'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    _order = 'create_date desc'
    _check_company_auto = True
    
    employee                  = fields.Many2one('hr.employee', string='Employee', required=True, tracking=True)
    related_company_id        = fields.Many2one('res.company', string='Company', related='employee.company_id', store=True)
    payroll                   = fields.Many2one('jtemployees.payrolls', string='Payroll', required=True, tracking=True)
    details                   = fields.One2many('jtemployees.payrolls.details', inverse_name="payroll_slip", string='Details', required=True, tracking=True, domain=[('deleted', '=', False)])
    warnings                  = fields.One2many('jtemployees.payrolls.swarning', inverse_name="payroll_slip", string='Warnings', tracking=True, domain=[('deleted', '=', False)])
    date_selected_from        = fields.Date(string='Date From', required=True, tracking=True)
    date_selected_to          = fields.Date(string='Date To', required=True, tracking=True)
    json_data                 = fields.Text(string='Json Data')
    
    total_amount              = fields.Float(string='Total amount', compute='_compute_total_amount')
    total_insurance_amount    = fields.Float(string='Total insurance amount',   tracking=True)
    total_shortages           = fields.Float(string='Total shortages',   tracking=True)
    total_absences            = fields.Float(string='Total absences',   tracking=True)
    total_overtime            = fields.Float(string='Total overtime',    tracking=True)
    total_allocations         = fields.Float(string='Total allocations', tracking=True)
    total_rewards             = fields.Float(string='Total rewards',     tracking=True)
    total_deductions          = fields.Float(string='Total deductions',  tracking=True)
    total_unpaid              = fields.Float(string='Total unpaid',  tracking=True)
    
    approved                  = fields.Boolean(string='Active', default=False, tracking=True)
    shortages_ignored         = fields.Boolean(string='Shortages ignored', default=False, tracking=True)
    shortages_ignored_from    = fields.Date(string='Ignore shorages from', help='Period for ignored shortages', tracking=True)
    shortages_ignored_to      = fields.Date(string='Ignore shorages to', help='Period for ignored shortages', tracking=True)
    
    match_period_amount       = fields.Boolean(string='Force match period salary', default=False, tracking=True)
    default_working_schedule  = fields.Many2one('resource.calendar', string='Fallover Working schedule', help="This working schedule for in case some dont have working schedule and want to just pass the payroll", tracking=True)
    
    deleted                   = fields.Boolean(string='Active', default=False, tracking=True)
    
    def _compute_total_amount(self):
        for record in self:
            total_amount = 0.0
            details = self.env['jtemployees.payrolls.details'].search([("payroll_slip", "=", record.id)])
            for detail in details:
                total_amount += detail.amount
            
            if total_amount < 0:
                total_amount = 0
            record.total_amount = total_amount
            
    
    def create(self, vals):
        # children amount
        if not isinstance(vals, list):
            vals = [vals]        
        for item in vals:
            shortages_ignored = item['shortages_ignored']
            shortages_ignored_from = item['shortages_ignored_from']
            shortages_ignored_to = item['shortages_ignored_to']
            
            default_working_schedule = False
            if 'default_working_schedule' in item:
                default_working_schedule = item['default_working_schedule']
            
            match_period_amount = item['match_period_amount']
            employee = item['employee']
            employee = self.env['hr.employee'].browse([employee])
            employee = employee[0]
            
            total_amount = 0
            
            payroll_slip = super(payrolls_slips, self).create(vals)            
            
            # getting work schedule
            calendar = employee.resource_calendar_id
            
            if not calendar and default_working_schedule:
                calendar = self.env['resource.calendar'].sudo().search([('id', '=', default_working_schedule)], limit=1)
            
            hour_per_day = employee.company_id.jt_general_hours_per_day
            
            days = False
            
            working_days_of_week = []
            expected_hours = 0
            shortage_hours = 0
            absence_hours = 0
            over_time = 0
            
            total_unpaid_leave_hours = 0
            total_unpaid_time_hours = 0
            total_unpaid_details = []
            total_overtime_details = []
            
            date_selected_from = datetime.strptime(item['date_selected_from'], '%Y-%m-%d')
            date_selected_to =   datetime.strptime(item['date_selected_to'],   '%Y-%m-%d')
            
            if employee.jt_reassign_at:
                jt_reassign_at = employee.jt_reassign_at
                date_selected_to = date_selected_to.replace(
                    year=jt_reassign_at.year,
                    month=jt_reassign_at.month,
                    day=jt_reassign_at.day
                )
            
            salary_start_date = employee.company_id.jt_salary_start_date
            shortage_multiplying_factor = float(employee.company_id.jt_shortage_multiplying_factor)
            absence_multiplying_factor = float(employee.company_id.jt_absence_multiplying_factor)
            overtime_multiplying_factor = float(employee.company_id.jt_overtime_multiplying_factor)
            
            if date_selected_from < salary_start_date:
                date_selected_from = salary_start_date
                
            # to solve an issue where users request leaves before payrolls period
            leave_requests_from_factor = date_selected_from - timedelta(days=30)
            
            attendance_requests_days = self.env['jtemployees.requests'].search([('deleted', '=', False),
                                                                                ('employee_id', '=', employee.id),
                                                            ('date_from', '>=', leave_requests_from_factor),
                                                            ('request_type', 'in', ["admin_leave","paid_leave","unpaid_leave","remote_working_leave", "field_work_leave", "official_mission_leave", "external_training_leave", "client_visit_leave", "government_errand_leave"]),
                                                            ('manager_approved', '=', 'approved'),
                                                            ('hr_approved', '=', 'approved')
                                                            ])
            attendance_requests_times = self.env['jtemployees.requests'].search([('deleted', '=', False),
                                                                                 ('employee_id', '=', employee.id),
                                                            ('datetime_from', '>=', date_selected_from),
                                                            ('datetime_to', '<', date_selected_to + timedelta(days=1)),
                                                            ('request_type', 'in', ["admin_time_off","paid_time_off","unpaid_time_off","over_time","field_work_time_off", "official_mission_time_off", "external_training_time_off", "client_visit_time_off", "government_errand_time_off"]),
                                                            ('manager_approved', '=', 'approved'),
                                                            ('hr_approved', '=', 'approved')
                                                            ])
            
            # getting work attendance
            attendances = self.env['hr.attendance'].search([('employee_id', '=', employee.id),
                                                            ('check_in', '>=', (date_selected_from - timedelta(days=1)).strftime('%Y-%m-%d'))])
            
            # getting extra shortages
            extra_shortages = self.env['jtemployees.extrashortages'].search([('deleted', '=', False),('employee_ids', 'in', [employee.id]),
                                                            ('date', '>=', date_selected_from),('date', '<=', date_selected_to),])
            
            # holidays
            holidays = self.env['jtemployees.holiday'].sudo().search([('deleted', '=', False),('date', '>=', date_selected_from),('date', '<=', date_selected_to),])
            holidays_dates = []
            for holiday in holidays:
                if employee.department_id.id in holiday.departments.ids:
                    holidays_dates.append(holiday.date)
                
            # Initialize the current date to the start date
            current_date = date_selected_from
                        
            shortages_details = []
            absences_details = []
            shortages_warnings = []
            
            # because the days of the month for the iraqi people is 30
            try:
                jt_fixed_salary_amount = int(employee.jt_fixed_salary_amount)
                amount_per_hour = jt_fixed_salary_amount / 30 / hour_per_day
                amount_per_day = jt_fixed_salary_amount / 30
            except:
                raise exceptions.ValidationError(str(employee.name) + " has no salary or no working schedule")
            
            # in case we want to match the days with the salary
            if match_period_amount:
                days = (date_selected_to - date_selected_from).days
                days += 1 # because The last day itself is not completed yet, so it is not counted.
                if days < 0 or days > 31:
                    raise exceptions.ValidationError("bad dates, please set the start date in settings to be before the selected date")
                jt_fixed_salary_amount = int((jt_fixed_salary_amount / 30) * days)
                
            join_date_time = employee.jt_migrate_date
            if not join_date_time:
                join_date_time = employee.jt_join_date
            # if the user has join date after the period
            if not join_date_time:
                raise exceptions.ValidationError("Employee (" + str(employee.name) + ") has no join date")
            if join_date_time > date_selected_from.date():
                days = (date_selected_to.date() - join_date_time).days
                days += 1 # because The last day itself is not completed yet, so it is not counted.
                if days > 30:
                    days = 30
                if days < 0:
                    raise exceptions.ValidationError("bad dates, please set the start date in settings to be before the selected date (" + str(employee.name) + ")")
                jt_fixed_salary_amount = int((jt_fixed_salary_amount / 30) * days)
                
            
            insurance_amount = 0
            if not employee.jt_separate_insu_salary:
                insurance_salary_amount = employee.jt_fixed_salary_amount
            else:
                insurance_salary_amount = employee.jt_fixed_insurance_salary_amount
            insurance_percentage = employee.company_id.jt_insurance_percentage
            if insurance_salary_amount:
                insurance_amount = insurance_salary_amount * (insurance_percentage / 100)
                
            monthly_shortage_grace_hours = float(employee.company_id.jt_monthly_shortage_grace_hours)
            shortage_flex_eligibility_threshold = float(employee.company_id.jt_shortage_flex_eligibility_threshold / 60)
            shortage_minimum_warning = float(employee.company_id.jt_shortage_minimum_warning / 60)
            
            work_schedule = calendar
            
            debug_data = []
            # We calculate the total shortage for the given period
            while current_date <= date_selected_to:
                
                if current_date.date() < join_date_time:
                    current_date += timedelta(days=1)
                    continue
                
                debug_object = {
                    "current_date": current_date,
                    "join_date_time": join_date_time,
                    "amount_per_hour": amount_per_hour,
                    "days": days,
                    "holidays_dates": holidays_dates,
                    "expected_shortage": 0
                }
                
                expected_shortage = 0
                
                calculate_shortage = True
                passed_day = self.env['jtemployees.passed.days'].sudo().search([('employee_id', '=', employee.id), ('date', '=', current_date.date())], limit=1)
                if passed_day and passed_day.is_day_off:
                    calculate_shortage = False
                else:
                    working_days_of_week = []
                    
                    work_schedule = False
                    # we try to get the working schedule from attendnance checkin
                    for attendance in attendances:
                        user_tz = "Asia/Baghdad"
                        checkin = attendance.check_in + timedelta(minutes=30)
                        check_in_local = fields.Datetime.context_timestamp(
                            self.with_context(tz=user_tz),
                            checkin
                        )
                        if check_in_local.strftime('%Y-%m-%d') == current_date.strftime('%Y-%m-%d'):
                            work_schedule = attendance.jt_work_schedule
                            break
                        
                    if not work_schedule:
                        work_schedule = calendar
                        
                        
                    if not work_schedule:
                        raise exceptions.ValidationError("The employee " + str(employee.name) + " does not have working schedule for the period " + str(current_date))
                            
                    if not work_schedule.tz:
                        raise exceptions.ValidationError("The employee " + str(employee.name) + " has a working schedule without a timezone for the period " + str(current_date))
                    try:
                        timezone(calendar.tz)
                    except:
                        raise exceptions.ValidationError("The employee " + str(employee.name) + " has a working schedule without a timezone for the period " + str(current_date))

                            
                    hour_per_day = work_schedule.hours_per_day
                    
                    debug_object['work_schedule'] = work_schedule.id
                    debug_object['hour_per_day'] = hour_per_day
                    
                    # Get working days to use in the shortage checking
                    for schedual_day in work_schedule.attendance_ids:
                        if schedual_day.day_period != "break" and schedual_day.dayofweek not in working_days_of_week:
                            working_days_of_week.append(schedual_day.dayofweek)
                            
                    debug_object['working_days_of_week'] = working_days_of_week
                            
                    calculate_shortage = str(current_date.weekday()) in working_days_of_week
                    
                debug_object['calculate_shortage'] = False
                if calculate_shortage:
                    
                    debug_object['calculate_shortage'] = True
                    
                    # we get the shortage
                    expected_hours   += hour_per_day
                    expected_shortage = hour_per_day
                    
                    # is holiday
                    if current_date.date() in holidays_dates:
                        debug_object['expected_shortage_zero_reason'] = "holidays_dates"
                        expected_shortage = 0
                    # handle leave days
                    elif payroll_slip.has_leave_day(attendance_requests_days, current_date):
                        debug_object['expected_shortage_zero_reason'] = "has_leave_day"
                        expected_shortage = 0
                    # handle unpaid days (deducted by salary)
                    elif payroll_slip.has_unpaid_leave_day(attendance_requests_days, current_date):
                        debug_object['expected_shortage_zero_reason'] = "has_unpaid_leave_day"
                        expected_shortage = 0
                        total_unpaid_leave_hours += 1
                        leave_request = payroll_slip.has_unpaid_leave_day(attendance_requests_days, current_date)
                        total_unpaid_details.append({
                            'name': str(leave_request.name) + " (" + str(leave_request.get_type_label()) + ")",
                            'date': current_date.strftime('%Y-%m-%d'),
                            "hours": hour_per_day,
                            'amount': amount_per_day
                        })
                    # if we ignore shortages
                    elif shortages_ignored:
                        debug_object['expected_shortage_zero_reason'] = "shortages_ignored"
                        expected_shortage = 0
                    # if we ignore shortages for periods
                    elif shortages_ignored_from and shortages_ignored_to and shortages_ignored_from <= current_date.date() <= shortages_ignored_to:
                        debug_object['expected_shortage_zero_reason'] = "shortages_ignored_from_to"
                        debug_object['shortages_ignored_from'] = shortages_ignored_from
                        debug_object['shortages_ignored_to'] = shortages_ignored_to
                        expected_shortage = 0
                    # we calculate shortages
                    else:
                        expected_shortage = hour_per_day
                        for attendance in attendances:
                            user_tz = "Asia/Baghdad"
                            checkin = attendance.check_in + timedelta(minutes=30)
                            check_in_local = fields.Datetime.context_timestamp(
                                self.with_context(tz=user_tz),
                                checkin
                            )
                            if check_in_local.strftime('%Y-%m-%d') == current_date.strftime('%Y-%m-%d'):
                                if attendance.jt_worked_hours:
                                    working_hours = attendance.jt_worked_hours
                                    expected_shortage = attendance.jt_shortage_hours
                                else:
                                    working_hours = self.working_for_attendance(work_schedule, attendance)
                                    expected_shortage = expected_shortage - working_hours
                                debug_object['working_hours'] = working_hours
                                break
                        
                        debug_object_extra_shortages = []
                        for extra_shortage in extra_shortages:
                            if extra_shortage.date.strftime('%Y-%m-%d') == current_date.strftime('%Y-%m-%d'):
                                debug_object_extra_shortages.append({
                                    "hours": extra_shortage.hours,
                                    "extra_shortage": extra_shortage.id
                                })
                                expected_shortage = extra_shortage.hours
                                break
                        debug_object['extra_shortage'] = debug_object_extra_shortages
                            
                    debug_object_timeoffs = []
                    total_timeoff = 0
                    for timeoff_type in ["admin_time_off", "paid_time_off", "unpaid_time_off", "field_work_time_off", "official_mission_time_off", "external_training_time_off", "client_visit_time_off", "government_errand_time_off"]:
                        timeoff_calculated  = payroll_slip.time_off_calculate(attendance_requests_times, timeoff_type, current_date, work_schedule)
                        if timeoff_calculated > 0:
                            total_timeoff = total_timeoff + timeoff_calculated
                            
                            if timeoff_type == "unpaid_time_off":
                                total_unpaid_time_hours += timeoff_calculated
                                total_unpaid_details.append({
                                    'name': '',
                                    'date': current_date.strftime('%Y-%m-%d'),
                                    "hours": timeoff_calculated,
                                    'amount': amount_per_hour * timeoff_calculated
                                })
                                
                                debug_object_timeoffs.append({
                                    "hours": timeoff_calculated,
                                    'amount': amount_per_hour * timeoff_calculated
                                })
                    debug_object['timeoffs'] = debug_object_timeoffs
                            
                    expected_shortage -= total_timeoff
                    
                    if expected_shortage > 0:
                        if expected_shortage < shortage_flex_eligibility_threshold and monthly_shortage_grace_hours >= expected_shortage:
                            monthly_shortage_grace_hours -= expected_shortage
                            debug_object['grace_hours_shortages'] = expected_shortage
                            if expected_shortage >= shortage_minimum_warning:
                                shortages_warnings.append({
                                    'name': 'Shortage Warning',
                                    "date":  current_date.strftime('%Y-%m-%d'),
                                    "shortage_hours": expected_shortage
                                })
                            expected_shortage = 0
                    
                    if expected_shortage > 0.02:
                      
                        
                        if expected_shortage >= hour_per_day:
                            absences_details.append({
                                'name': 'absence',
                                "date":  current_date.strftime('%Y-%m-%d'),
                                "hours": expected_shortage,
                                'amount': amount_per_hour * expected_shortage * absence_multiplying_factor
                            })
                            absence_hours += expected_shortage
                        else:
                            shortages_details.append({
                                'name': 'shortage',
                                "date":  current_date.strftime('%Y-%m-%d'),
                                "hours": expected_shortage,
                                'amount': amount_per_hour * expected_shortage * shortage_multiplying_factor
                            })
                            shortage_hours += expected_shortage
                    else:
                        if expected_shortage > 0:
                            debug_object['shortage_less_0_02'] = expected_shortage
                        expected_shortage = 0
                    
                day_over_time   = self.time_calculate(attendance_requests_times, 'over_time', current_date, work_schedule)
                    
                overtime_amount = 0
                if day_over_time:
                    debug_object['day_over_time'] = day_over_time
                    if employee.jt_has_overtime_limits and day_over_time > employee.jt_overtime_max_hours:
                        day_over_time = employee.jt_overtime_max_hours
                        debug_object['day_over_time_after_max'] = day_over_time
                    over_time += day_over_time
                    
                    if employee.jt_has_overtime_limits and employee.jt_overtime_amount_hourly > 0:
                        overtime_amount = day_over_time * employee.jt_overtime_amount_hourly
                        debug_object['overtime_amount_limited'] = employee.jt_overtime_amount_hourly
                    else:
                        overtime_amount = day_over_time * amount_per_hour  * overtime_multiplying_factor
                        debug_object['overtime_amount_calculation'] = {
                            "day_over_time": day_over_time,
                            "amount_per_hour": amount_per_hour,
                            "day_over_tiovertime_multiplying_factorme": overtime_multiplying_factor,
                        }
                    
                    debug_object['overtime_amount'] = overtime_amount
                    
                    total_overtime_details.append({
                        'name': '',
                        'date': current_date.strftime('%Y-%m-%d'),
                        "hours": day_over_time,
                        'amount': overtime_amount
                    })
                    
                debug_object['expected_shortage'] = expected_shortage
                current_date += timedelta(days=1)
                if shortage_hours < 0:
                    shortage_hours = 0
                    
                if absence_hours < 0:
                    absence_hours = 0
                    
                debug_data.append(debug_object)
                
            payroll_slip.json_data = str(debug_data)
                    
            absence_amount = absence_hours * amount_per_hour * absence_multiplying_factor
            shortage_amount = shortage_hours * amount_per_hour * shortage_multiplying_factor
            unpaid_requests_amount = total_unpaid_leave_hours * amount_per_day
            unpaid_requests_amount += total_unpaid_time_hours * amount_per_hour
            
            if employee.jt_has_overtime_limits and employee.jt_overtime_amount_hourly > 0:
                overtime_amount = over_time * employee.jt_overtime_amount_hourly
            else:
                overtime_amount = over_time * amount_per_hour  * overtime_multiplying_factor
        
            total_amount = jt_fixed_salary_amount
            # we start calculating the amount
            self.env['jtemployees.payrolls.details'].create([{
                    "payroll_slip": payroll_slip.id,
                    "name": "Base salary",
                    "slug": "base_salary",
                    "json_details": "[]",
                    "amount": jt_fixed_salary_amount
                }])
            
            if not employee.jt_ignore_shortages:
                if absence_amount > 0:
                    total_amount -=  absence_amount
                    payroll_slip.total_absences = absence_amount
                    detail = self.env['jtemployees.payrolls.details'].create([{
                        "payroll_slip": payroll_slip.id,
                        "name": "Absences",
                        "slug": "absences",
                        "json_details": str(absences_details),
                        "amount": absence_amount * -1
                    }])
                    for i in absences_details:
                        self.env['jtemployees.payrolls.dsubs'].create([{
                            "details_id": detail.id,
                            "name": i['name'],
                            "date": i['date'],
                            "hours": i['hours'],
                            "amount": i['amount']
                        }])
                    
                if shortage_amount > 0:
                    total_amount -=  shortage_amount
                    payroll_slip.total_shortages = shortage_amount
                    detail = self.env['jtemployees.payrolls.details'].create([{
                        "payroll_slip": payroll_slip.id,
                        "name": "Shortages",
                        "slug": "shortages",
                        "json_details": str(shortages_details),
                        "amount": shortage_amount * -1
                    }])
                    for i in shortages_details:
                        self.env['jtemployees.payrolls.dsubs'].create([{
                            "details_id": detail.id,
                            "name": i['name'],
                            "date": i['date'],
                            "hours": i['hours'],
                            "amount": i['amount']
                        }])
                    
            for warning in shortages_warnings:
                self.env['jtemployees.payrolls.swarning'].create([{
                    "payroll_slip": payroll_slip.id,
                    "name": warning['name'],
                    "date": warning['date'],
                    "shortage_hours": warning['shortage_hours']
                }])
                
            if insurance_amount > 0:
                total_amount -= insurance_amount
                payroll_slip.total_insurance_amount = insurance_amount
                detail = self.env['jtemployees.payrolls.details'].create([{
                    "payroll_slip": payroll_slip.id,
                    "name": "Insurance Amount",
                    "slug": "insurance_amount",
                    "json_details": "",
                    "amount": insurance_amount * -1
                }])
            
            if unpaid_requests_amount > 0:
                total_amount -= unpaid_requests_amount
                payroll_slip.total_unpaid = unpaid_requests_amount
                detail = self.env['jtemployees.payrolls.details'].create([{
                    "payroll_slip": payroll_slip.id,
                    "name": "Unpaid requests",
                    "slug": "unpaid_requests",
                    "json_details": str(total_unpaid_details),
                    "amount": unpaid_requests_amount * -1
                }])
                
                for i in total_unpaid_details:
                    self.env['jtemployees.payrolls.dsubs'].create([{
                        "details_id": detail.id,
                        "name": i['name'],
                        "date": i['date'],
                        "hours": i['hours'],
                        "amount": i['amount']
                    }])
                
            if overtime_amount > 0:
                total_amount += overtime_amount
                payroll_slip.total_overtime = overtime_amount
                detail = self.env['jtemployees.payrolls.details'].create([{
                    "payroll_slip": payroll_slip.id,
                    "name": "Overtime",
                    "slug": "overtime",
                    "json_details": str(total_overtime_details),
                    "amount": overtime_amount
                }])
                
                for i in total_overtime_details:
                    self.env['jtemployees.payrolls.dsubs'].create([{
                        "details_id": detail.id,
                        "name": i['name'],
                        "date": i['date'],
                        "hours": i['hours'],
                        "amount": i['amount']
                    }])
            
            hr_certificate_payroll_calculate = config.get('hr_certificate_payroll_calculate')
            hr_marital_payroll_calculate     = config.get('hr_marital_payroll_calculate')
            hr_children_payroll_calculate    = config.get('hr_children_payroll_calculate')
            hr_seniority_payroll_calculate   = config.get('hr_seniority_payroll_calculate')
            # maried state
            if hr_marital_payroll_calculate:
                if employee.marital == "married":
                    married_amount = self.calculate_marital(employee)
                    if married_amount > 0:
                        total_amount += married_amount
                        self.env['jtemployees.payrolls.details'].create([{
                            "payroll_slip": payroll_slip.id,
                            "name": "Marital Allocations",
                            "slug": "marital_allocations",
                            "json_details": "",
                            "amount": married_amount
                        }])
            # children state
            if hr_children_payroll_calculate:
                if employee.children > 0:
                    children_amount = self.calculate_children(employee, employee.children)
                    if children_amount > 0:
                        total_amount += children_amount
                        self.env['jtemployees.payrolls.details'].create([{
                            "payroll_slip": payroll_slip.id,
                            "name": "Children Allocations",
                            "slug": "children_allocations",
                            "json_details": "",
                            "amount": children_amount
                        }])
                
            # certification state
            if hr_certificate_payroll_calculate:
                certificate_amount = 0
                certificate = self.env['jtemployees.certificates'].search([("name", "=", employee.certificate)])
                if not certificate and len(certificate) > 1:
                    certificate = certificate[0]
                    certificate_amount = certificate.amount
                    total_amount += certificate_amount
                    self.env['jtemployees.payrolls.details'].create([{
                        "payroll_slip": payroll_slip.id,
                        "name": "Certificate Allocations",
                        "slug": "certificate_allocations",
                        "json_details": "",
                        "amount": certificate_amount
                    }])
                
            # seniority state
            if hr_seniority_payroll_calculate:            
                seniority_amount = self.calculate_seniority(employee, employee.jt_fixed_salary_amount)
                total_amount += seniority_amount
                if seniority_amount > 0:
                    self.env['jtemployees.payrolls.details'].create([{
                            "payroll_slip": payroll_slip.id,
                            "name": "Seniority Allocations",
                            "slug": "seniority_allocations",
                            "json_details": "",
                            "amount": seniority_amount
                        }])
                
            
            extra_allocations = self.env['jtemployees.extraallocations'].search([('deleted', '=', False),('employee_id', '=', employee.id)])
                
            allocations = 0
            for extra_allocation in extra_allocations:
                total_amount += extra_allocation.amount
                allocations += extra_allocation.amount
                
                self.env['jtemployees.payrolls.details'].create([{
                        "payroll_slip": payroll_slip.id,
                        "name": extra_allocation.name,
                        "slug": "allocations",
                        "json_details": "[]",
                        "amount": extra_allocation.amount
                    }])
                
            payroll_slip.total_allocations = allocations
                
            bounces = self.env['jtemployees.bounces'].search([('deleted', '=', False),('employee_ids', 'in', [employee.id]),
                                                        ('date', '>=', date_selected_from.strftime('%Y-%m-%d')),('date', '<=', date_selected_to.strftime('%Y-%m-%d')),])
                
            bounce_details = []
            total_rewards = 0
            total_deductions = 0
            for bounce in bounces:
                total_amount += bounce.amount
                
                if bounce.amount > 0:
                    total_rewards += bounce.amount
                    
                if bounce.amount < 0:
                    total_deductions += bounce.amount * -1
                
                bounce_details.append({
                    "name": bounce.name,
                    "date":  bounce.date.strftime('%Y-%m-%d'),
                    "hours": 0,
                    "amount": bounce.amount
                })
                
                detail = self.env['jtemployees.payrolls.details'].create([{
                        "payroll_slip": payroll_slip.id,
                        "name": bounce.name,
                        "slug": "bounce_deduction",
                        "json_details": str(bounce_details),
                        "amount": bounce.amount
                    }])
                
                for i in bounce_details:
                    self.env['jtemployees.payrolls.dsubs'].create([{
                        "details_id": detail.id,
                        "name": i['name'],
                        "date": i['date'],
                        "hours": i['hours'],
                        "amount": i['amount']
                    }])
            payroll_slip.total_rewards = total_rewards
            payroll_slip.total_deductions = total_deductions
        return payroll_slip
    
    def working_for_attendance(self, calendar, attendance):
        requested_day = attendance.check_in.weekday()
        actual_worked_hours = 0
        required_working_hours = calendar.hours_per_day
        working_hours = attendance.worked_hours
        
        if calendar.flexible_hours:
            diff = attendance.check_out - attendance.check_in
            actual_worked_hours = diff.total_seconds() / 3600
            if actual_worked_hours > required_working_hours:
                actual_worked_hours = required_working_hours
        else:
            workday_hour_from      = None
            workday_hour_to        = None
            
                
            if calendar.jt_start_time or calendar.jt_end_time:
                if calendar.jt_start_time < calendar.jt_end_time:
                    required_working_hours = calendar.jt_end_time - calendar.jt_start_time
                if calendar.jt_start_time > calendar.jt_end_time:
                    required_working_hours = (24 - calendar.jt_start_time) + calendar.jt_end_time
                    
                workday_hour_from = calendar.jt_start_time
                workday_hour_to   = calendar.jt_end_time
            
                if (requested_day == 0 and not calendar.jt_has_monday) \
                or (requested_day == 1 and not calendar.jt_has_tuesday) \
                or (requested_day == 2 and not calendar.jt_has_wednesday) \
                or (requested_day == 3 and not calendar.jt_has_thursday) \
                or (requested_day == 4 and not calendar.jt_has_friday) \
                or (requested_day == 5 and not calendar.jt_has_saturday) \
                or (requested_day == 6 and not calendar.jt_has_sunday):
                    return required_working_hours
            else:
                actual_worked_hours = working_hours
                if working_hours > required_working_hours:
                    actual_worked_hours = required_working_hours
                return required_working_hours
            
            # setting timezone
            local_tz = timezone(calendar.tz)
            check_in = attendance.check_in.astimezone(local_tz)
            if not attendance.check_out:
                return 0
            check_out = attendance.check_out.astimezone(local_tz)
            
            
            employee_checkin = check_in.hour + (check_in.minute / 60)
            employee_checkout = check_out.hour + (check_out.minute / 60)
            
            actual_worked_hours = required_working_hours
            
            if workday_hour_from < workday_hour_to: # normal day
                if check_in.strftime('%Y-%m-%d') == check_out.strftime('%Y-%m-%d'):
                    if employee_checkin > workday_hour_from:
                        actual_worked_hours = actual_worked_hours - (employee_checkin - workday_hour_from)
                    if employee_checkout < workday_hour_to:
                        actual_worked_hours = actual_worked_hours - (workday_hour_to - employee_checkout)
                else:
                    if employee_checkout < workday_hour_to:
                        delta = check_out - check_in
                        actual_worked_hours = delta.total_seconds() / 3600
                        if actual_worked_hours > required_working_hours:
                            actual_worked_hours = required_working_hours
                        if workday_hour_to > employee_checkout and (workday_hour_to - employee_checkout) < 8:
                            actual_worked_hours = actual_worked_hours - (workday_hour_to - employee_checkout)
                        elif workday_hour_from < employee_checkin and (employee_checkin - workday_hour_from) < 8:
                            actual_worked_hours = actual_worked_hours - (employee_checkin - workday_hour_from)
                
            else: # night shift
                if employee_checkin > workday_hour_from:
                    actual_worked_hours = actual_worked_hours - (employee_checkin - workday_hour_from)
                if employee_checkout < workday_hour_to:
                    actual_worked_hours = actual_worked_hours - (workday_hour_to - employee_checkout)
                else:
                    #if same day checkout but the work schedule is overlapping 
                    if check_in.strftime('%Y-%m-%d') == check_out.strftime('%Y-%m-%d'):
                        actual_worked_hours = actual_worked_hours - ((workday_hour_to + 24) - employee_checkout)
                        
                    
            if actual_worked_hours > required_working_hours:
                actual_worked_hours = required_working_hours
                
        if actual_worked_hours < 0:
            actual_worked_hours = 0
        
        return actual_worked_hours
    
    def has_leave_day(self, attendance_requests_days, current_date):
        for attendance_requests_day in attendance_requests_days:
            if attendance_requests_day.request_type == "unpaid_leave":
                continue
            date_from = attendance_requests_day.date_from
            date_to = attendance_requests_day.date_to - timedelta(days=1)
            has_leave = date_from <= current_date.date() <= date_to
            #has_leave = date_from.strftime('%Y-%m-%d') == current_date.strftime('%Y-%m-%d') or date_to.strftime('%Y-%m-%d') == current_date.strftime('%Y-%m-%d')
            if has_leave:
                return attendance_requests_day
        return False
    
    def has_unpaid_leave_day(self, attendance_requests_days, current_date):
        for attendance_requests_day in attendance_requests_days:
            if attendance_requests_day.request_type != "unpaid_leave":
                continue
            date_from = attendance_requests_day.date_from
            date_to = attendance_requests_day.date_to - timedelta(days=1)
            has_leave = date_from <= current_date.date() <= date_to
            #has_leave = date_from.strftime('%Y-%m-%d') == current_date.strftime('%Y-%m-%d') or date_to.strftime('%Y-%m-%d') == current_date.strftime('%Y-%m-%d')
            if has_leave:
                return attendance_requests_day
        return False
    
    def has_location_checkin_checkout_hours(self, location_checkins, current_date):
        for location_checkin_day in location_checkins:
            if location_checkin_day.day == current_date.date():
                delta = location_checkin_day.check_out - location_checkin_day.check_in
                return delta.total_seconds() / 3600
        return 0
    
    def time_calculate(self, attendance_requests_times, request_type, current_date, calendar):
        total_hours = 0
        try:
            local_tz = timezone(calendar.tz)
        except:
            return 0
        for attendance_requests_time in attendance_requests_times:
            if attendance_requests_time.request_type == request_type:
                datetime_from = attendance_requests_time.datetime_from.astimezone(local_tz)
                datetime_to = attendance_requests_time.datetime_to.astimezone(local_tz)
        
                has_leave = datetime_from.strftime('%Y-%m-%d') == current_date.strftime('%Y-%m-%d')
                if has_leave:
                    time_difference = datetime_to - datetime_from
                    total_hours += time_difference.total_seconds() / 3600
                    if total_hours < 0:
                        return 0
        return total_hours
    
    def time_off_calculate(self, attendance_requests_times, request_type, current_date, calendar):
        total_seconds = 0
        local_tz = timezone(calendar.tz)   # Asia/Baghdad

        # VERY important step
        checkin_date = current_date.astimezone(local_tz)
        checkout_date = current_date.astimezone(local_tz)

        jt_start_time = calendar.jt_start_time
        jt_end_time   = calendar.jt_end_time
        
        has_flexible_hours = calendar.flexible_hours

        if jt_end_time < jt_start_time:
            checkout_date = checkout_date + timedelta(days=1)

        hours = int(jt_start_time)
        minutes = int((jt_start_time - hours) * 60)

        checkin_date = checkin_date.replace(
            hour=hours,
            minute=minutes,
            second=0,
            microsecond=0
        )

        hours = int(jt_end_time)
        minutes = int((jt_end_time - hours) * 60)

        checkout_date = checkout_date.replace(
            hour=hours,
            minute=minutes,
            second=0,
            microsecond=0
        )
        
        for attendance_requests_time in attendance_requests_times:
            if attendance_requests_time.request_type == request_type:
                
                datetime_from = attendance_requests_time.datetime_from.astimezone(local_tz)
                datetime_to = attendance_requests_time.datetime_to.astimezone(local_tz)
                
                if has_flexible_hours:
                    has_timeoff = datetime_from.strftime('%Y-%m-%d') == current_date.strftime('%Y-%m-%d')
                    if has_timeoff:
                        time_difference = datetime_to - datetime_from
                        total_seconds += time_difference.total_seconds()
                else:
                    # Find the overlap between request period and attendance period
                    overlap_start = max(datetime_from, checkin_date)
                    overlap_end   = min(datetime_to, checkout_date)
                    
                    if overlap_start <= overlap_end:
                        total_seconds += (overlap_end - overlap_start).total_seconds()
                    
        return total_seconds / 3600.0
    
    def calculate_marital(self, employee):
        marital_amount = employee.company_id.jt_marital_amount
        if marital_amount:
            marital_amount = float(marital_amount)
        else:
            marital_amount = 0
        return marital_amount
    
    def calculate_children(self, employee, number_of_children):
        child_amount = employee.company_id.jt_child_amount
        max_childs = employee.company_id.jt_max_childs
        if max_childs:
            max_childs = int(max_childs)
        else:
            max_childs = 0
        if child_amount:
            child_amount = float(child_amount)
        else:
            child_amount = 0
            
        if number_of_children > max_childs:
            number_of_children = max_childs
            
        return child_amount * number_of_children
    
    def calculate_seniority(self, employee, grade_amount):
        start_date = employee.company_id.jt_seniority_start_date
        increase_percentage_per_year = employee.company_id.jt_seniority_percentage_per_year
        max_years = employee.company_id.jt_seniority_max_year
        
        if start_date:
            years_since_joined = employee.jt_join_date.year - start_date.year
        else:
            years_since_joined = 0
        if max_years:
            max_years = int(max_years)
        else:
            max_years = 0
        
        if years_since_joined < 0:
            years_since_joined = 0
        if years_since_joined > max_years:
            years_since_joined = max_years
            
        if increase_percentage_per_year:
            increase_percentage_per_year = int(increase_percentage_per_year)
        else:
            increase_percentage_per_year = 0
            
        increase_percentage = increase_percentage_per_year * years_since_joined
        
        if increase_percentage < 1:
            return 0
        increase_percentage = increase_percentage / 100
        seniority = grade_amount * increase_percentage
        return seniority
    
    def unlink(self):
        for record in self:
            record.sudo().write({'deleted': True, 'approved': False})
        return True
        
    
