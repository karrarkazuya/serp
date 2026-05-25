from odoo import models, fields, exceptions, api
from datetime import datetime, timedelta, time

class JTEmployeesRequests(models.Model):
    _name = 'jtemployees.requests'
    _description = 'HR Requests'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    _order = 'create_date desc'
    _check_company_auto = True
    
    name                    = fields.Char(string='Title', required=True, tracking=True)
    description             = fields.Text(string='Description', tracking=True)
    parent_id               = fields.Many2one('hr.employee', string='Parent Manager', required=True, tracking=True) # parent manager
    employee_id             = fields.Many2one('hr.employee', string='Employee', tracking=True)
    company_id              = fields.Many2one('res.company', string='Company', related='employee_id.company_id', store=True)
    
    request_period_type     = fields.Selection([("leave", 'Full day'), ("timeoff", 'Timeoff'), ("overtime", 'Overtime')], string="Period Type", default='leave', required=True, )
    request_type            = fields.Selection([
                                ("admin_leave", 'Admin Leave'), 
                                ("unpaid_leave", 'Unpaid Leave'), 
                                ("paid_leave", 'Normal Leave'),
                                ("remote_working_leave", 'Remote Working Leave'),
                                ("field_work_leave", 'Field Work Leave'),
                                ("official_mission_leave", 'Official Mission Leave'),
                                ("external_training_leave", 'External Training Leave'),
                                ("client_visit_leave", 'Client Visit Leave'),
                                ("government_errand_leave", 'Government Errand Leave'),
                                
                                ("admin_time_off", 'Admin Time Off'),
                                ("paid_time_off", 'Normal Time Off'),
                                ("unpaid_time_off", 'Unpaid Time Off'),
                                ("field_work_time_off", 'Field Work Time Off'),
                                ("official_mission_time_off", 'Official Mission Time Off'),
                                ("external_training_time_off", 'External Training Time Off'),
                                ("client_visit_time_off", 'Client Visit Time Off'),
                                ("government_errand_time_off", 'Government Errand Time Off'),
                                
                                ("over_time", 'Over Time')], default='paid_leave', required=True, tracking=True)
    
    request_type_leave = fields.Selection(selection=[("admin_leave", 'Admin'), 
                                ("unpaid_leave", 'Unpaid'), 
                                ("paid_leave", 'Normal'),
                                ("remote_working_leave", 'Remote Working'),
                                ("field_work_leave", 'Field Work'),
                                ("official_mission_leave", 'Official Mission'),
                                ("external_training_leave", 'External Training'),
                                ("client_visit_leave", 'Client Visit'),
                                ("government_errand_leave", 'Government Errand')], store=False, string="Request Type")
    request_type_timeoff = fields.Selection(selection=[("admin_time_off", 'Admin'),
                                ("paid_time_off", 'Normal'),
                                ("unpaid_time_off", 'Unpaid'),
                                ("field_work_time_off", 'Field Work'),
                                ("official_mission_time_off", 'Official Mission'),
                                ("external_training_time_off", 'External Training'),
                                ("client_visit_time_off", 'Client Visit'),
                                ("government_errand_time_off", 'Government Errand')], store=False, string="Request Type")
    
    
    request_type_overtime = fields.Selection(selection=[("over_time", 'Over Time')], store=False, string="Request Type")


    extra_image             = fields.Image(string='Image')
    manager_approved        = fields.Selection([("pending", 'Pending'), ("approved", 'Approved'), ("rejected", 'Rejected')], string='Manager Response', default='pending', required=True, tracking=True)
    hr_approved             = fields.Selection([("pending", 'Pending'), ("approved", 'Approved'), ("rejected", 'Rejected')], string='HR Response', default='pending', required=True, tracking=True)
    state                   = fields.Selection([("pending", 'Pending'), ("rejected", 'Rejected'), ("manager_approved", 'Manager approved'), ("hr_approved", 'HR approved')], string='Final state', default='pending', required=True, tracking=True)
    
    
    manager_note            = fields.Text(string='Manager Note', tracking=True)
    hr_note                 = fields.Text(string='HR Note', tracking=True)
    
    datetime_from           = fields.Datetime(string='From Date', tracking=True)
    datetime_to             = fields.Datetime(string='To Date', tracking=True)
    
    date_from               = fields.Date(string='From Date', tracking=True)
    date_to                 = fields.Date(string='To Date', tracking=True)
    
    general_from            = fields.Datetime(string='From Date')
    general_to              = fields.Datetime(string='To Date')
    
    days_requested          = fields.Float(string='Days requested', compute='_compute_total_days')
    minutes_requested       = fields.Float(string='Minutes requested', compute='_compute_total_hours')

    submitted_notification  = fields.Boolean(string='Submitted notification', default=False)
    deleted                 = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    is_manager_user         = fields.Boolean(compute='_compute_is_manager_user')
    readable_period         = fields.Char('Requested period', compute='_compute_readable_period')
    general_readable_period = fields.Char('Period')
    
    
    check_in                = fields.Datetime(string='Checkin', tracking=True)
    check_out               = fields.Datetime(string='Checkout', tracking=True)
    fingerprint             = fields.Many2one('jtemployees.fd.log', string='Fingerprint', tracking=True)
    
    duplicated_requests = fields.Many2many(
        'jtemployees.requests',
        compute='_compute_duplicated_requests',
        string='Duplicated Requests',
    )

    @api.depends('employee_id', 'request_type')
    def _compute_duplicated_requests(self):
        for rec in self:
            if not rec.employee_id or not rec.request_type:
                rec.duplicated_requests = False
                continue
            duplicates = self.search([
                ('employee_id', '=', rec.employee_id.id),
                ('request_type', '=', rec.request_type),
                ('datetime_from', '=', rec.datetime_from),
                ('datetime_to', '=', rec.datetime_to),
                ('date_from', '=', rec.date_from),
                ('date_from', '=', rec.date_from),
                ('id', '!=', rec.id or 0),
                ('deleted', '=', False)
            ])
            rec.duplicated_requests = duplicates

    @api.onchange('request_type_leave', 'request_type_timeoff', 'request_type_overtime', 'request_period_type')
    def _onchange_request_type_proxy(self):
        if self.request_period_type == 'leave':
            self.request_type = self.request_type_leave or False
        elif self.request_period_type == 'timeoff':
            self.request_type = self.request_type_timeoff or False
        elif self.request_period_type == 'overtime':
            self.request_type = self.request_type_overtime or False
    
    def compute_general_dates(self):
        for record in self:
            general_from = ""
            general_to = ""
            if record.request_type in ["admin_leave", "paid_leave", "unpaid_leave", "remote_working_leave", "field_work_leave", "official_mission_leave", "external_training_leave", "client_visit_leave", "government_errand_leave"] and record.date_from and record.date_to:
                datetime.combine(record.date_from, datetime.min.time())
                general_from = datetime.combine(record.date_from, datetime.min.time())
                general_to = datetime.combine(record.date_to, datetime.min.time())
            elif record.datetime_from and record.datetime_to:
                general_from = record.datetime_from
                general_to = record.datetime_to
            super(JTEmployeesRequests, record).write({
                "general_from": general_from,
                "general_to": general_to,
                "general_readable_period": record.readable_period
            })
 
    def _compute_is_manager_user(self):
        if self.env.user.employee_id:
            my_employee = self.env.user.employee_id
        else:
            auth_user = self.env['jtapi.users'].sudo().search([('user_id', '=', self.env.user.id)], limit=1)
            my_employee = self.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            
        for record in self:
            record.is_manager_user = (my_employee.id == record.parent_id.id)
    
    @api.depends("request_type", "date_to", "date_from", "datetime_from", "datetime_to")
    def _compute_readable_period(self):
        jt_minimum_timeoff_request_minutes = self.env.user.company_id.jt_minimum_timeoff_request_minutes
        for record in self:
            if record.request_type in ["admin_time_off","paid_time_off","unpaid_time_off","over_time","field_work_time_off", "official_mission_time_off", "external_training_time_off", "client_visit_time_off", "government_errand_time_off"]:
                if record.datetime_from and record.datetime_to:
                    from_time = record.datetime_from
                    to_time   = record.datetime_to
                    duration_hours = self.get_hours(str(from_time), str(to_time))
                    duration_minutes = self.get_minutes(str(from_time), str(to_time))
                    
                    if record.request_type in ['over_time']:
                        if duration_minutes < 30 or duration_hours > 20:
                            record.readable_period = "Period must be between 30 minutes and 20 hours"
                        else:
                            record.readable_period = ""
                    else:
                        if duration_minutes < jt_minimum_timeoff_request_minutes or duration_hours > 4:
                            record.readable_period = "Period must be between " + str(jt_minimum_timeoff_request_minutes) + " minutes and 4 hours"
                        else:
                            record.readable_period = ""
                            
                    if record.readable_period == "":
                        hours = int(duration_minutes // 60)
                        mins = int(round(duration_minutes % 60))
                        formatted_time = f"{hours:02d}:{mins:02d}"
                        record.readable_period = formatted_time
                else:
                    record.readable_period = "Please select period"
            if record.request_type in ["admin_leave","unpaid_leave","paid_leave", "remote_working_leave", "field_work_leave", "official_mission_leave", "external_training_leave", "client_visit_leave", "government_errand_leave"]:
                if record.date_from and record.date_to:
                    duration = record.date_to - record.date_from
                    if duration.days < 0:
                        record.readable_period = "Please select correct period"
                    else:
                        record.readable_period = duration.days
                else:
                    record.readable_period = "Please select period"
                    
            self.compute_general_dates()
            
    def is_duplicated(self):
        duplicates = self.search([
                ('employee_id', '=', self.employee_id.id),
                ('request_type', '=', self.request_type),
                ('datetime_from', '=', self.datetime_from),
                ('datetime_to', '=', self.datetime_to),
                ('date_from', '=', self.date_from),
                ('date_from', '=', self.date_from),
                ('id', '!=', self.id or 0),
                ('state', '!=', 'rejected'),
                ('deleted', '=', False)
            ])
        return len(duplicates) > 0
    
    def create(self, vals):
        
        jt_minimum_timeoff_request_minutes = self.env.user.company_id.jt_minimum_timeoff_request_minutes
        
        if self.env.user.employee_id:
            my_employee = self.env.user.employee_id
        else:
            auth_user = self.env['jtapi.users'].sudo().search([('user_id', '=', self.env.user.id)], limit=1)
            my_employee = self.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            
        calendar = my_employee.resource_calendar_id
        if not calendar:
            raise exceptions.ValidationError("No working schedule found for you. Please contact HR department.")
            
        vals['employee_id'] = my_employee.id
        
        if my_employee.jt_request_approval:
            vals['parent_id'] = my_employee.jt_request_approval.id
        else:
            vals['parent_id'] = my_employee.parent_id.id
            
        if not vals['parent_id']:
            raise exceptions.ValidationError("You do not have a manager above you thus you can not request.")
        if vals['request_type'] in ["admin_time_off", "paid_time_off", "unpaid_time_off", "over_time","field_work_time_off", "official_mission_time_off", "external_training_time_off", "client_visit_time_off", "government_errand_time_off"] \
            and 'datetime_from' in vals and vals['datetime_from'] and 'datetime_to' in vals and vals['datetime_to']:
            from_time = datetime.strptime(vals['datetime_from'], "%Y-%m-%d %H:%M:%S")
            to_time = datetime.strptime(vals['datetime_to'], "%Y-%m-%d %H:%M:%S")
            if to_time < from_time:
                raise exceptions.ValidationError("Please select correct time")
            
            duration_hours = self.get_hours(str(from_time), str(to_time))
            duration_minutes = self.get_minutes(str(from_time), str(to_time))
            if vals['request_type'] in ['over_time']:
                if duration_minutes < 30 or duration_hours > 20:
                    raise exceptions.ValidationError("Selected time is not correct, it must not be less than 30 minutes and not greater than 20 hours")
            else:
                if duration_minutes < jt_minimum_timeoff_request_minutes or duration_hours > 4:
                    raise exceptions.ValidationError("Selected time is not correct, it must not be less than " + str(jt_minimum_timeoff_request_minutes) + " minutes and not greater than 4 hours")
                
        elif vals['request_type'] in ["admin_leave", "unpaid_leave", "paid_leave", "remote_working_leave", "field_work_leave", "official_mission_leave", "external_training_leave", "client_visit_leave", "government_errand_leave"] \
            and 'date_from' in vals and vals['date_from'] and 'date_to' in vals and vals['date_to']:
            from_date = datetime.strptime(vals['date_from'], "%Y-%m-%d")
            to_date = datetime.strptime(vals['date_to'], "%Y-%m-%d")
            if to_date < from_date:
                raise exceptions.ValidationError("Please select correct time")
            duration = to_date - from_date
            if duration.days < 0:
                raise exceptions.ValidationError("Selected date is not correct")
        else:
            raise exceptions.ValidationError("Selected date is not correct")
            
        if vals['request_type'] == "paid_leave":
            days = self.get_days(str(vals['date_from']), str(vals['date_to']))
            if my_employee.jt_leavedays < days:
                raise exceptions.ValidationError("employee does not have enough leave days")
            
        if vals['request_type'] == "paid_time_off":
            minutes = self.get_minutes(str(vals['datetime_from']), str(vals['datetime_to']))
            if my_employee.jt_timeoff < minutes:
                raise exceptions.ValidationError("employee does not have enough timeoff balance")
        
        request = super(JTEmployeesRequests, self).create(vals)
        #if request.is_duplicated():
        #    raise exceptions.ValidationError("Request is already submitted previously")
        self.compute_requests()
        
        request.activity_schedule(
            'jtemployees.mail_act_hr_request_approval',
            summary="HR Request",
            note=f"A new request from sub employee {request.employee_id.name} sent to you. Please review it.",
            user_id=request.parent_id.user_id.id,
            date_deadline=fields.Date.today(),
        )
        
        
        return request
    
    
    def approve_request(self):
        for record in self:
            record.manager_approved = 'approved'
            
    def reject_request(self):
        for record in self:
            record.manager_approved = 'rejected'
            
    def approve_hr_request(self):
        for record in self:
            record.hr_approved = 'approved'
            
    def reject_hr_request(self):
        for record in self:
            record.hr_approved = 'rejected'
    
    def write(self, vals):
        for record in self:
            if self.deleted:
                raise exceptions.ValidationError("You are not allowed to modify deleted request")
            
            if "hr_note" in vals:
                if self.env.user.has_group('jtemployees.group_requests_approver'):
                    if (record.hr_approved == "approved" and record.manager_approved == "approved") or (record.manager_approved == "rejected" or record.hr_approved == "rejected"):
                        request = super(JTEmployeesRequests, self).write({
                            "hr_note": vals['hr_note']
                        })
                        return request
                else:
                    raise exceptions.ValidationError("You are not allowed to modify this field, only HR can change this field")
            
            if record.hr_approved == "approved" and record.manager_approved == "approved":
                raise exceptions.ValidationError("You are not allowed to update an approved request")
            if record.manager_approved == "rejected" or record.hr_approved == "rejected":
                raise exceptions.ValidationError("This request has been rejected, you can not modify a rejected request")
            if "manager_approved" in vals:
                if not record.is_manager_user:
                    raise exceptions.ValidationError("You are not allowed to modify this field")
            if "hr_approved" in vals or "parent_id" in vals:
                if not self.env.user.has_group('jtemployees.group_requests_approver'):
                    raise exceptions.ValidationError("You are not allowed to modify this field, only HR can change this field")
            
            # if one is rejected, its totally rejected
            if ("manager_approved" in vals and vals['manager_approved'] == "rejected") or ("hr_approved" in vals and vals['hr_approved'] == "rejected"): 
                record.activity_unlink(['jtemployees.mail_act_hr_request_approval'])
                record.activity_unlink(['jtemployees.mail_act_hr_manage_request_approval'])
                request = super(JTEmployeesRequests, self).write({
                    "state": "rejected",
                    "hr_approved": "rejected",
                    "manager_approved": "rejected"
                    })
                
                try:
                    mobileUser = self.env['jtapi.users'].sudo().search([('employee', '=', record.employee_id.id)], limit=1)
                    if mobileUser:
                        if ("manager_approved" in vals and vals['manager_approved'] == "rejected"):
                            mobileUser._notify("تحديث حالة طلب", "تم رفض طلبك من قبل المدير الخاص بك, رقم الطلب هو " + str(record.id), "hr_request", record.id, "jtemployees.requests")
                        if ("hr_approved" in vals and vals['hr_approved'] == "rejected"):
                            mobileUser._notify("تحديث حالة طلب", "تم رفض طلبك من قبل ادارة الموارد البشرية, رقم الطلب هو " + str(record.id), "hr_request", record.id, "jtemployees.requests")
                except:
                    pass
                
                return request
            
            if ("manager_approved" in vals and vals['manager_approved'] == "approved"):
                vals['state'] = "manager_approved"
                
            if ("hr_approved" in vals and vals['hr_approved'] == "approved"):
                vals['state']            = "hr_approved"
                vals['manager_approved'] = "approved"
                try:
                    mobileUser = self.env['jtapi.users'].sudo().search([('employee', '=', record.employee_id.id)], limit=1)
                    if mobileUser:
                        mobileUser._notify("تحديث حالة طلب", "تم قبول طلبك من قبل ادارة الموارد البشرية, رقم الطلب هو " + str(record.id), "hr_request", record.id, "jtemployees.requests")
                except:
                    pass
              
            request = super(JTEmployeesRequests, self).write(vals)
            record = self.env['jtemployees.requests'].browse([record.id])
            
            if record.hr_approved == "approved" and record.manager_approved == "approved":
                
                record.activity_feedback(['jtemployees.mail_act_hr_manage_request_approval'])
                
                employees = self.env['hr.employee'].browse([record.employee_id.id])
                
                if record.request_type == "paid_leave":
                    days = self.get_days(str(record.date_from), str(record.date_to))
                    if employees.jt_leavedays < days:
                        raise exceptions.ValidationError("employee does not have enough leave days")
                    employees.write(
                        {
                            "jt_leavedays": employees.jt_leavedays - days
                        }
                    )
                    
                if record.request_type == "paid_time_off":
                    minutes = self.get_minutes(str(record.datetime_from), str(record.datetime_to))
                    if employees.jt_timeoff < minutes:
                        raise exceptions.ValidationError("employee does not have enough timeoff balance")
                    employees.write(
                        {
                            "jt_timeoff": employees.jt_timeoff - minutes
                        }
                    )
           
            if record.manager_approved == 'approved' and record.hr_approved == 'pending' and not record.submitted_notification:
                
                record.activity_feedback(['jtemployees.mail_act_hr_request_approval'])
                group = self.env.ref('jtemployees.group_requests_approver')
                users = self.env['res.users'].search([
                    ('groups_id', 'in', [group.id]),
                    ('company_id', '=', record.create_uid.company_id.id),
                ])
                for user in users:
                    record.activity_schedule(
                        'jtemployees.mail_act_hr_manage_request_approval',
                        summary="HR Employee Request",
                        note=f"A new request from {record.employee_id.name} has been created. Please review it.",
                        user_id=user.id,
                        date_deadline=fields.Date.today(),
                    )
                record.submitted_notification = True
            return request
        return True
    
    def get_days(self, from_date: str, to_date: str):
        from_date_obj = datetime.strptime(from_date, "%Y-%m-%d")
        to_date_obj = datetime.strptime(to_date, "%Y-%m-%d")
        new_date = to_date_obj - from_date_obj
        return new_date.days
    
    def get_hours(self, from_datetime: str, to_datetime: str):
        from_date_obj = datetime.strptime(from_datetime, "%Y-%m-%d %H:%M:%S")
        to_date_obj = datetime.strptime(to_datetime, "%Y-%m-%d %H:%M:%S")
        new_date = to_date_obj - from_date_obj
        return new_date.total_seconds() / 3600
    
    def get_minutes(self, from_datetime: str, to_datetime: str):
        from_date_obj = datetime.strptime(from_datetime, "%Y-%m-%d %H:%M:%S")
        to_date_obj = datetime.strptime(to_datetime, "%Y-%m-%d %H:%M:%S")
        new_date = to_date_obj - from_date_obj
        return new_date.total_seconds() / 60
        
    @api.depends("date_to", "date_from")
    def _compute_total_days(self):
        for record in self:
            record.days_requested = 0
            if record.date_to and record.date_from:
                new_date = record.date_to - record.date_from
                record.days_requested = new_date.days
                
    @api.depends("datetime_to", "datetime_from")
    def _compute_total_hours(self):
        for record in self:
            record.minutes_requested = 0
            if record.datetime_to and record.datetime_from:
                new_date = record.datetime_to - record.datetime_from
                record.minutes_requested = new_date.total_seconds() / 60
        
    def unlink(self):
        for record in self:
            if record.hr_approved == "approved" and record.manager_approved == "approved":
                raise exceptions.ValidationError("You are not allowed to delete an approved request")
            record.write({'deleted': True})
        return True


    def add_new_allocations(self):
        print("Monthly New Requests Allocations executed")
        
        employees = self.env['hr.employee'].sudo().search([])
        for employee in employees:
            
            monthly_leave_requests       = int(employee.company_id.jt_monthly_leave_requests)
            max_monthly_leave_requests   = int(employee.company_id.jt_max_monthly_leave_requests)
            monthly_timeoff_requests     = int(employee.company_id.jt_monthly_timeoff_requests)
            max_monthly_timeoff_requests = int(employee.company_id.jt_max_monthly_timeoff_requests)
            
            monthly_timeoff_requests = monthly_timeoff_requests * 60
            max_monthly_timeoff_requests = max_monthly_timeoff_requests * 60
            
            jt_leavedays = monthly_leave_requests
            jt_timeoff = monthly_timeoff_requests
                
            if employee.jt_leavedays and employee.jt_leavedays > 0:
                jt_leavedays += employee.jt_leavedays
            
            if employee.jt_timeoff and employee.jt_timeoff > 0:
                jt_timeoff += employee.jt_timeoff
                
            if jt_leavedays > max_monthly_leave_requests:
                jt_leavedays = max_monthly_leave_requests
                
            if jt_timeoff > max_monthly_timeoff_requests:
                jt_timeoff = max_monthly_timeoff_requests
                
                
            last_record = self.env['jtemployees.log.allocations'].sudo().search([('employee_id', '=', employee.id)], order='create_date desc', limit=1)

            is_older_than_15_days = False
            if last_record and last_record.create_date:
                limit_date = fields.Datetime.now() - timedelta(days=15)
                is_older_than_15_days = last_record.create_date < limit_date
                
            if not last_record or is_older_than_15_days:
                self.env['jtemployees.log.allocations'].sudo().create({
                    "name": employee.name,
                    "employee_id": employee.id,
                    "jt_timeoff": jt_timeoff,
                    "jt_leavedays": jt_leavedays,
                })
                
                employee.sudo().write({
                    "jt_timeoff": jt_timeoff,
                    "jt_leavedays": jt_leavedays,
                })
            
    def compute_requests(self):
        for item in self:
            check_in = item.datetime_from
            if not check_in:
                check_in = item.date_from
                target_date = check_in
            else:
                target_date = check_in.date()
            
            start_dt = datetime.combine(target_date, time.min)
            end_dt = datetime.combine(target_date, time.max)

            att = self.env['hr.attendance'].sudo().search([
                ("employee_id", "=", item.employee_id.id),
                ("check_in", ">=", start_dt),
                ("check_in", "<=", end_dt),
            ], order="check_in desc", limit=1)
            
            if not att:
                att = self.env['hr.attendance'].sudo().search([
                    ("employee_id", "=", item.employee_id.id),
                    ("check_in", "<=", check_in),
                ], order="check_in desc", limit=1)
            
            if att:
                fingerprint_log = self.env['jtemployees.fd.log'].sudo().search([('employee_id', '=', item.employee_id.id), ('attendance_id', '=', att.id)], limit=1)
                
                fingerprint_log_id = False
                if fingerprint_log:
                    fingerprint_log_id = fingerprint_log.id
                super(JTEmployeesRequests, item).write({
                    "check_in": att.check_in,
                    "check_out": att.check_out,
                    "fingerprint": fingerprint_log_id
                })
                
    def sync_checkins_fingerprints(self):
        items = self.env['jtemployees.requests'].with_user(1).sudo().search([('fingerprint', '=', False)])
        for item in items:
            item.compute_requests()
            
    def get_type_label(self):
        """Returns the human-readable label for a selection field value."""
        if self.request_type:
            # Get the selection list for the field
            selection = self._fields['request_type'].selection
            # Convert the list of tuples into a dictionary
            selection_dict = dict(selection)
            # Return the label, or the value itself if the key is not found
            return selection_dict.get(self.request_type, self.request_type)
        return self.request_type
            
